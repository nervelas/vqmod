<?php
declare(strict_types=1);

/** Orquesta la generación: sufijo opcional, cadena de proveedores, ajuste y guardado. */
final class Forja
{
    private Store $store;
    private Settings $settings;

    public function __construct(Store $store, Settings $settings)
    {
        $this->store = $store;
        $this->settings = $settings;
    }

    /**
     * Genera una sola imagen (las variaciones se piden una por una desde el navegador).
     *
     * @param array{prompt:string,negative:string,width:int,height:int,seed:int,format:string,
     *              realism:bool,batch_id:string,provider:string} $input
     * @return array{record:array,notes:array,failures:array}
     */
    public function generateOne(array $input): array
    {
        $prompt = (string) $input['prompt'];
        if ($prompt === '') {
            throw new ProviderError('Escribe un prompt antes de generar.', 'peticion');
        }
        $realism = !empty($input['realism']);
        // El prompt viaja EXACTO; solo se añade el sufijo si la persona activó el interruptor.
        $finalPrompt = $realism ? $this->withRealism($prompt) : $prompt;

        $width = max(64, min(4096, (int) $input['width']));
        $height = max(64, min(4096, (int) $input['height']));
        $format = $this->resolveFormat((string) $input['format']);
        $seed = (int) $input['seed'];
        $negative = (string) $input['negative'];

        $providers = $this->providerChain((string) ($input['provider'] ?? ''));
        if (!$providers) {
            throw new ProviderError(
                'No hay ningún proveedor activo. Actívalos en el panel de administración.',
                'configuracion'
            );
        }

        $request = new GenRequest($finalPrompt, $negative, $width, $height, $seed, $format);
        $failures = [];
        $notes = [];

        foreach ($providers as $provider) {
            try {
                $started = microtime(true);
                $result = $provider->generate($request);
                $elapsed = round(microtime(true) - $started, 1);
                $notes = array_merge($notes, $result->notes);
                if ($failures) {
                    $notes[] = 'Se usó ' . $provider->label() . ' porque falló el proveedor anterior.';
                }
                $record = $this->persist($provider, $result, $request, [
                    'realism' => $realism,
                    'batch_id' => (string) ($input['batch_id'] ?? ''),
                    'original_prompt' => $prompt,
                    'elapsed' => $elapsed,
                    'notes' => $notes,
                    'failures' => $failures,
                ]);
                $this->store->usageIncrement($provider->id());
                $this->prune();
                return ['record' => $record, 'notes' => $notes, 'failures' => $failures];
            } catch (ProviderError $e) {
                Logger::write('provider', $provider->id() . ' falló: ' . $e->getMessage());
                $failures[] = ['provider' => $provider->id(), 'label' => $provider->label(), 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                Logger::exception($e);
                $failures[] = [
                    'provider' => $provider->id(),
                    'label' => $provider->label(),
                    'message' => $provider->label() . ': error inesperado, revisa el log.',
                ];
            }
        }

        $summary = implode(' ', array_map(static fn (array $f): string => $f['message'], $failures));
        throw new ProviderError(
            'No se pudo generar la imagen con ningún proveedor. ' . $summary,
            'todos'
        );
    }

    public function withRealism(string $prompt): string
    {
        $suffix = trim($this->settings->get('realism_suffix'));
        if ($suffix === '') {
            return $prompt;
        }
        return rtrim($prompt, " ,.\t\n") . ', ' . $suffix;
    }

    /** @return ImageProvider[] */
    private function providerChain(string $preferred): array
    {
        $chain = ProviderRegistry::active($this->settings);
        if ($preferred !== '') {
            $first = null;
            $rest = [];
            foreach ($chain as $provider) {
                if ($provider->id() === $preferred) {
                    $first = $provider;
                } else {
                    $rest[] = $provider;
                }
            }
            if ($first !== null) {
                array_unshift($rest, $first);
                return $rest;
            }
        }
        return $chain;
    }

    private function resolveFormat(string $format): string
    {
        $format = strtolower($format) === 'jpeg' ? 'jpg' : strtolower($format);
        if (!in_array($format, Imaging::FORMATS, true)) {
            $format = $this->settings->get('default_format');
        }
        if (!Imaging::formatSupported($format)) {
            return Imaging::formatSupported('png') ? 'png' : 'jpg';
        }
        return $format;
    }

    /** Ajusta al tamaño exacto, guarda archivo y miniatura, y registra el historial. */
    private function persist(ImageProvider $provider, ProviderResult $result, GenRequest $req, array $extra): array
    {
        $id = Support::uuid();
        $format = $req->format;
        $bytes = $result->bytes;
        $notes = $extra['notes'];

        if (Imaging::available()) {
            $adjusted = Imaging::toExact($bytes, $req->width, $req->height, $format);
            if ($adjusted !== '') {
                $bytes = $adjusted;
            }
        } else {
            $notes[] = 'Sin GD ni Imagick: la imagen se guardó con el tamaño que entregó el proveedor.';
        }
        [$finalW, $finalH] = Imaging::dimensions($bytes);
        if ($finalW === 0) {
            $finalW = $req->width;
            $finalH = $req->height;
        }

        $ext = $format === 'jpg' ? 'jpg' : $format;
        $fileName = $id . '.' . $ext;
        $filePath = PF_STORAGE . '/images/' . $fileName;
        if (@file_put_contents($filePath, $bytes, LOCK_EX) === false) {
            throw new ProviderError('No se pudo guardar la imagen: revisa los permisos de storage/images.', 'disco');
        }
        @chmod($filePath, 0640);

        $thumbName = '';
        if (Imaging::available()) {
            $thumbBytes = Imaging::thumbnail($bytes, 420);
            $thumbExt = Imaging::formatSupported('webp') ? 'webp' : 'jpg';
            $thumbName = $id . '.' . $thumbExt;
            if (@file_put_contents(PF_STORAGE . '/thumbs/' . $thumbName, $thumbBytes, LOCK_EX) === false) {
                $thumbName = '';
            } else {
                @chmod(PF_STORAGE . '/thumbs/' . $thumbName, 0640);
            }
        }

        $row = [
            'id' => $id,
            'created_at' => date('Y-m-d H:i:s'),
            'created_ts' => time(),
            'batch_id' => (string) $extra['batch_id'],
            'prompt' => (string) $extra['original_prompt'],
            'negative' => $req->negative,
            'provider' => $provider->id(),
            'provider_label' => $provider->label(),
            'model' => $result->model,
            'width' => $finalW,
            'height' => $finalH,
            'source_width' => $result->sourceWidth,
            'source_height' => $result->sourceHeight,
            'seed' => $req->seed,
            'format' => $format,
            'realism' => !empty($extra['realism']) ? 1 : 0,
            'file' => $fileName,
            'thumb' => $thumbName,
            'bytes' => strlen($bytes),
            'meta' => [
                'prompt_enviado' => $req->prompt,
                'requested_width' => $req->width,
                'requested_height' => $req->height,
                'segundos' => $extra['elapsed'],
                'notas' => $notes,
                'fallos_previos' => $extra['failures'],
            ],
        ];
        $this->store->imageInsert($row);
        return $row;
    }

    /** Mantiene el historial dentro del límite configurado y borra los archivos sobrantes. */
    private function prune(): void
    {
        $keep = $this->settings->int('keep_history');
        if ($keep <= 0) {
            return;
        }
        $total = $this->store->imageCount();
        if ($total <= $keep) {
            return;
        }
        $extra = $this->store->imageList(50, $keep);
        foreach ($extra as $row) {
            self::deleteFiles($row);
            $this->store->imageDelete((string) $row['id']);
        }
    }

    public static function deleteFiles(array $row): void
    {
        $file = (string) ($row['file'] ?? '');
        $thumb = (string) ($row['thumb'] ?? '');
        if ($file !== '' && is_file(PF_STORAGE . '/images/' . $file)) {
            @unlink(PF_STORAGE . '/images/' . $file);
        }
        if ($thumb !== '' && is_file(PF_STORAGE . '/thumbs/' . $thumb)) {
            @unlink(PF_STORAGE . '/thumbs/' . $thumb);
        }
    }
}
