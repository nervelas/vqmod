<?php use App\Models\Cuota; ?>
<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,340px)">
  <div class="columna">
    <?php if ($enRevision !== []): ?>
      <div class="aviso-caja info">
        <?= ico('reloj', 20) ?>
        <div><strong>Ya tiene <?= count($enRevision) ?> comprobante(s) en revisión</strong>
          La administración los revisará y le avisaremos en cuanto se apruebe.</div>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <?= csrf() ?>
      <div class="tarjeta">
        <div class="tarjeta-cab"><h3>Reportar mi pago</h3></div>
        <div class="tarjeta-cuerpo">
          <p class="texto-2" style="font-size:.92rem">
            Ya realizó su depósito o transferencia. Adjunte aquí su comprobante y la administración lo aplicará
            a su estado de cuenta. Recibirá su recibo oficial por correo.
          </p>
          <div class="campos">
            <div class="campo">
              <label for="monto">¿Cuánto pagó? *</label>
              <input type="number" id="monto" name="monto" step="0.01" min="0.01" required inputmode="decimal"
                     value="<?= $saldo > 0 ? e(number_format($saldo, 2, '.', '')) : '' ?>">
            </div>
            <div class="campo">
              <label for="fecha">Fecha del pago *</label>
              <input type="date" id="fecha" name="fecha" required max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="campo">
              <label for="metodo">Forma de pago *</label>
              <select id="metodo" name="metodo" required>
                <option value="deposito">Depósito bancario</option>
                <option value="transferencia">Transferencia</option>
                <option value="efectivo">Efectivo en administración</option>
                <option value="tarjeta">Tarjeta</option>
                <option value="linea">Pago en línea</option>
              </select>
            </div>
            <div class="campo">
              <label for="banco">Banco</label>
              <input type="text" id="banco" name="banco" maxlength="90" placeholder="Banco Industrial">
            </div>
            <div class="campo campo-ancho">
              <label for="referencia">Número de boleta o referencia</label>
              <input type="text" id="referencia" name="referencia" maxlength="90">
            </div>
            <div class="campo campo-ancho">
              <label for="comprobante">Fotografía o PDF del comprobante *</label>
              <input type="file" id="comprobante" name="comprobante" accept="image/*,application/pdf" required
                     capture="environment" data-previa="#previa-comp">
              <img id="previa-comp" src="<?= e(url('/assets/img/vacio.svg')) ?>" alt="" hidden style="margin-top:10px;border-radius:var(--r-sm);max-height:220px">
              <span class="ayuda">Puede tomar la foto directamente con su teléfono.</span>
            </div>
            <div class="campo campo-ancho">
              <label for="notas">Comentario para la administración</label>
              <textarea id="notas" name="notas" rows="2" maxlength="400"></textarea>
            </div>
          </div>
        </div>
        <div class="tarjeta-pie fila-fin">
          <button class="btn btn-oro btn-lg" type="submit"><?= ico('subir', 18) ?> Enviar comprobante</button>
        </div>
      </div>
    </form>
  </div>

  <div class="columna">
    <article class="tarjeta">
      <div class="tarjeta-cuerpo centrado">
        <div class="mayus">Saldo pendiente</div>
        <div class="kpi-valor"><?= e(q($saldo)) ?></div>
        <?php if ($enlacePago !== ''): ?>
          <a class="btn btn-oro btn-bloque mt-2" href="<?= e($enlacePago) ?>" target="_blank" rel="noopener">
            <?= ico('tarjeta', 17) ?> Pagar en línea
          </a>
          <span class="ayuda">Se abrirá la plataforma de pagos del residencial.</span>
        <?php endif; ?>
      </div>
    </article>

    <?php if ($cuenta !== ''): ?>
      <article class="tarjeta">
        <div class="tarjeta-cab"><h3>Datos para depositar</h3></div>
        <div class="tarjeta-cuerpo compacto">
          <p style="font-size:.92rem;margin:0"><?= nl2br(e($cuenta)) ?></p>
          <button class="btn btn-claro btn-sm btn-bloque mt-2" type="button" data-copiar="<?= e($cuenta) ?>">
            <?= ico('archivo', 15) ?> Copiar los datos
          </button>
        </div>
      </article>
    <?php endif; ?>

    <?php if ($cargos !== []): ?>
      <article class="tarjeta">
        <div class="tarjeta-cab"><h3>Lo que debo</h3></div>
        <div class="tarjeta-cuerpo compacto">
          <ul class="lista-limpia">
            <?php foreach ($cargos as $c): ?>
              <li class="item-lista" style="padding:9px 0">
                <div class="crecer" style="font-size:.86rem"><?= e(recortar((string) $c['descripcion'], 34)) ?></div>
                <b class="num" style="font-size:.86rem"><?= e(q(Cuota::saldoCargo($c))) ?></b>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </article>
    <?php endif; ?>
  </div>
</div>
