/* PixelForge — lógica del estudio. Sin dependencias externas. */
(function () {
    'use strict';

    var app = document.getElementById('app');
    if (!app) { return; }

    var cfg = {};
    try {
        cfg = JSON.parse(app.getAttribute('data-config') || '{}');
    } catch (e) {
        cfg = {};
    }
    var BASE = cfg.base || '';
    var CSRF = cfg.csrf || '';

    var forma = document.getElementById('forma');
    var lienzo = document.getElementById('lienzo');
    var avisos = document.getElementById('avisos');
    var historial = document.getElementById('historial');
    var historialVacio = document.getElementById('historial-vacio');
    var btnMas = document.getElementById('mas-historial');
    var btnGenerar = document.getElementById('btn-generar');
    var listaPresets = document.getElementById('lista-presets');
    var modal = document.getElementById('modal');
    var modalContenido = document.getElementById('modal-contenido');
    var buscar = document.getElementById('buscar');

    var estado = { desde: 0, limite: 24, busqueda: '', total: 0, generando: false, peticion: 0 };

    // --- Utilidades --------------------------------------------------------
    function texto(valor) {
        var div = document.createElement('div');
        div.textContent = valor === null || valor === undefined ? '' : String(valor);
        return div.innerHTML;
    }

    function aviso(mensaje, tipo, persistente) {
        var caja = document.createElement('div');
        caja.className = 'aviso ' + (tipo || 'info');
        caja.textContent = mensaje;
        avisos.appendChild(caja);
        if (!persistente) {
            window.setTimeout(function () {
                caja.style.transition = 'opacity .4s ease';
                caja.style.opacity = '0';
                window.setTimeout(function () { caja.remove(); }, 400);
            }, 9000);
        }
        return caja;
    }

    function limpiarAvisos() {
        avisos.innerHTML = '';
    }

    function peticion(accion, datos, metodo) {
        var url = BASE + '/api.php?accion=' + encodeURIComponent(accion);
        var opciones = {
            method: metodo || 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        };
        if (opciones.method === 'POST') {
            var cuerpo = new FormData();
            cuerpo.append('csrf', CSRF);
            cuerpo.append('accion', accion);
            Object.keys(datos || {}).forEach(function (clave) {
                cuerpo.append(clave, datos[clave]);
            });
            opciones.body = cuerpo;
        } else if (datos) {
            var params = Object.keys(datos).map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(datos[k]);
            });
            if (params.length) { url += '&' + params.join('&'); }
        }
        return fetch(url, opciones).then(function (respuesta) {
            return respuesta.json().catch(function () {
                throw new Error('El servidor respondió algo inesperado. Revisa storage/logs/app.log.');
            }).then(function (json) {
                if (!respuesta.ok || !json.ok) {
                    throw new Error(json.error || 'Error ' + respuesta.status);
                }
                return json;
            });
        });
    }

    function uuid() {
        var s = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
        return s.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : ((r & 0x3) | 0x8);
            return v.toString(16);
        });
    }

    // --- Proporciones y tamaño --------------------------------------------
    var ancho = document.getElementById('ancho');
    var alto = document.getElementById('alto');

    Array.prototype.forEach.call(document.querySelectorAll('input[name="proporcion"]'), function (radio) {
        radio.addEventListener('change', function () {
            var partes = radio.value.split('x');
            ancho.value = partes[0];
            alto.value = partes[1];
        });
    });

    function marcarProporcionLibre() {
        Array.prototype.forEach.call(document.querySelectorAll('input[name="proporcion"]'), function (radio) {
            var partes = radio.value.split('x');
            radio.checked = (partes[0] === ancho.value && partes[1] === alto.value);
        });
    }
    ancho.addEventListener('input', marcarProporcionLibre);
    alto.addEventListener('input', marcarProporcionLibre);

    // --- Aviso de prompt negativo por proveedor ---------------------------
    var selProveedor = document.getElementById('proveedor');
    var avisoNegativo = document.getElementById('aviso-negativo');
    function actualizarAvisoNegativo() {
        var lista = cfg.proveedores || [];
        var elegido = selProveedor.value;
        var admiten = lista.filter(function (p) { return p.enabled && p.negative; })
            .map(function (p) { return p.label; });
        if (elegido) {
            var info = lista.filter(function (p) { return p.id === elegido; })[0];
            avisoNegativo.textContent = info && info.negative
                ? 'Este proveedor sí admite prompt negativo.'
                : 'Este proveedor ignora el prompt negativo (no forma parte de su API).';
            return;
        }
        avisoNegativo.textContent = admiten.length
            ? 'Lo admite: ' + admiten.join(', ') + '. Los demás proveedores lo ignoran.'
            : 'Ningún proveedor activo admite prompt negativo; se guardará como referencia en el historial.';
    }
    selProveedor.addEventListener('change', actualizarAvisoNegativo);
    actualizarAvisoNegativo();

    // --- Tarjetas ----------------------------------------------------------
    function limpiarLienzoVacio() {
        var vacio = lienzo.querySelector('.vacio-mensaje');
        if (vacio) {
            vacio.remove();
            lienzo.classList.remove('vacio');
        }
    }

    function tarjetaCargando(indice, total) {
        var placa = document.createElement('article');
        placa.className = 'placa';
        placa.innerHTML =
            '<div class="marco"><div class="cargando"><div class="cubeta"></div>' +
            '<p>Revelando ' + indice + ' de ' + total + '</p></div></div>';
        return placa;
    }

    function tarjeta(imagen) {
        var placa = document.createElement('article');
        placa.className = 'placa revelando';
        placa.setAttribute('data-id', imagen.id);

        var etiquetas = [
            '<b>' + texto(imagen.ancho + '×' + imagen.alto) + '</b>',
            '<b>seed ' + texto(imagen.seed) + '</b>',
            '<b>' + texto(imagen.formato.toUpperCase()) + '</b>',
            '<b class="destacado">' + texto(imagen.proveedor_label) + '</b>'
        ];
        if (imagen.realismo) { etiquetas.push('<b>realismo+</b>'); }
        if (imagen.ancho_origen && (imagen.ancho_origen !== imagen.ancho || imagen.alto_origen !== imagen.alto)) {
            etiquetas.push('<b>origen ' + texto(imagen.ancho_origen + '×' + imagen.alto_origen) + '</b>');
        }

        placa.innerHTML =
            '<div class="marco"><img class="revelado" alt="' + texto(imagen.prompt.slice(0, 110)) + '" src="' + texto(imagen.url) + '"></div>' +
            '<div class="datos">' +
            '<p class="prompt">' + texto(imagen.prompt) + '</p>' +
            '<div class="parametros">' + etiquetas.join('') + '</div>' +
            '<div class="acciones">' +
            '<a class="boton menudo" href="' + texto(imagen.descarga) + '">Descargar</a>' +
            '<button type="button" class="boton menudo" data-accion="regenerar">Regenerar</button>' +
            '<button type="button" class="boton menudo" data-accion="base">Usar como base</button>' +
            '<button type="button" class="boton menudo" data-accion="ver">Ver</button>' +
            '<button type="button" class="boton menudo peligro" data-accion="borrar">Borrar</button>' +
            '</div></div>';

        placa.addEventListener('click', function (evento) {
            var boton = evento.target.closest('[data-accion]');
            if (!boton) { return; }
            var accion = boton.getAttribute('data-accion');
            if (accion === 'regenerar') { regenerar(imagen, false); }
            if (accion === 'base') { usarComoBase(imagen); }
            if (accion === 'ver') { verDetalle(imagen); }
            if (accion === 'borrar') { borrar(imagen, placa); }
        });
        window.setTimeout(function () { placa.classList.remove('revelando'); }, 1700);
        return placa;
    }

    function usarComoBase(imagen) {
        document.getElementById('prompt').value = imagen.prompt;
        document.getElementById('negativo').value = imagen.negativo || '';
        document.getElementById('realismo').checked = !!imagen.realismo;
        ancho.value = imagen.ancho;
        alto.value = imagen.alto;
        document.getElementById('formato').value = imagen.formato;
        document.getElementById('seed').value = '';
        marcarProporcionLibre();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        document.getElementById('prompt').focus();
        aviso('Parámetros cargados en el formulario. La seed queda libre para variar el resultado.', 'info');
    }

    function regenerar(imagen, mismaSeed) {
        var datos = {
            prompt: imagen.prompt,
            negativo: imagen.negativo || '',
            ancho: imagen.ancho,
            alto: imagen.alto,
            formato: imagen.formato,
            realismo: imagen.realismo ? '1' : '0',
            seed: mismaSeed ? imagen.seed : '',
            proveedor: selProveedor.value,
            lote: uuid()
        };
        ejecutar([datos], 1);
    }

    function borrar(imagen, placa) {
        if (!window.confirm('¿Borrar esta imagen del historial? No se puede deshacer.')) { return; }
        peticion('eliminar', { id: imagen.id }).then(function () {
            if (placa) { placa.remove(); }
            var mini = historial.querySelector('[data-id="' + imagen.id + '"]');
            if (mini) { mini.remove(); }
            aviso('Imagen borrada.', 'ok');
        }).catch(function (error) {
            aviso(error.message, 'error');
        });
    }

    function verDetalle(imagen) {
        modalContenido.innerHTML =
            '<img src="' + texto(imagen.url) + '" alt="' + texto(imagen.prompt.slice(0, 110)) + '">' +
            '<p class="prompt">' + texto(imagen.prompt) + '</p>' +
            (imagen.negativo ? '<p class="ayuda">Negativo: ' + texto(imagen.negativo) + '</p>' : '') +
            '<div class="parametros" style="margin:12px 0">' +
            '<b>' + texto(imagen.ancho + '×' + imagen.alto) + '</b>' +
            '<b>seed ' + texto(imagen.seed) + '</b>' +
            '<b>' + texto(imagen.formato.toUpperCase()) + '</b>' +
            '<b>' + texto(imagen.peso) + '</b>' +
            '<b class="destacado">' + texto(imagen.proveedor_label) + '</b>' +
            '<b>' + texto(imagen.modelo) + '</b>' +
            '<b>' + texto(imagen.fecha) + '</b></div>' +
            '<div class="acciones">' +
            '<a class="boton menudo" href="' + texto(imagen.descarga) + '">Descargar</a>' +
            '<button type="button" class="boton menudo" data-cerrar>Cerrar</button></div>';
        modal.hidden = false;
    }

    modal.addEventListener('click', function (evento) {
        if (evento.target === modal || evento.target.hasAttribute('data-cerrar')) {
            modal.hidden = true;
        }
    });
    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') { modal.hidden = true; }
    });

    // --- Generación --------------------------------------------------------
    forma.addEventListener('submit', function (evento) {
        evento.preventDefault();
        if (estado.generando) { return; }
        var prompt = document.getElementById('prompt').value.trim();
        if (!prompt) {
            aviso('Escribe un prompt antes de generar.', 'error');
            return;
        }
        var anchoValor = parseInt(ancho.value, 10);
        var altoValor = parseInt(alto.value, 10);
        if (!anchoValor || !altoValor || anchoValor < 64 || altoValor < 64 || anchoValor > 4096 || altoValor > 4096) {
            aviso('El ancho y el alto deben estar entre 64 y 4096 píxeles.', 'error');
            return;
        }
        var variaciones = parseInt(document.querySelector('input[name="variaciones"]:checked').value, 10) || 1;
        var seedBase = document.getElementById('seed').value.trim();
        var lote = uuid();
        var trabajos = [];
        for (var i = 0; i < variaciones; i++) {
            trabajos.push({
                prompt: prompt,
                negativo: document.getElementById('negativo').value.trim(),
                ancho: anchoValor,
                alto: altoValor,
                formato: document.getElementById('formato').value,
                realismo: document.getElementById('realismo').checked ? '1' : '0',
                seed: seedBase === '' ? '' : String(parseInt(seedBase, 10) + i),
                proveedor: selProveedor.value,
                lote: lote
            });
        }
        ejecutar(trabajos, variaciones);
    });

    function ejecutar(trabajos, total) {
        limpiarAvisos();
        limpiarLienzoVacio();
        estado.generando = true;
        btnGenerar.disabled = true;
        btnGenerar.textContent = 'Revelando…';

        var indice = 0;
        function siguiente() {
            if (indice >= trabajos.length) {
                estado.generando = false;
                btnGenerar.disabled = false;
                btnGenerar.textContent = 'Generar imagen';
                recargarHistorial();
                return;
            }
            var trabajo = trabajos[indice];
            var marcador = tarjetaCargando(indice + 1, total);
            lienzo.insertBefore(marcador, lienzo.firstChild);
            if (indice === 0) {
                // En móvil el lienzo queda debajo del formulario: lo traemos a la vista.
                marcador.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            peticion('generar', trabajo).then(function (json) {
                marcador.replaceWith(tarjeta(json.imagen));
                (json.fallos || []).forEach(function (fallo) {
                    aviso(fallo.message, 'error', true);
                });
                (json.avisos || []).forEach(function (nota) {
                    aviso(nota, 'info');
                });
            }).catch(function (error) {
                marcador.remove();
                aviso(error.message, 'error', true);
            }).then(function () {
                indice++;
                siguiente();
            });
        }
        siguiente();
    }

    // --- Historial ---------------------------------------------------------
    function pintarHistorial(imagenes, reiniciar) {
        if (reiniciar) { historial.innerHTML = ''; }
        imagenes.forEach(function (imagen) {
            var figura = document.createElement('figure');
            figura.className = 'miniatura';
            figura.setAttribute('data-id', imagen.id);
            figura.style.margin = '0';
            figura.innerHTML =
                '<img loading="lazy" alt="' + texto(imagen.prompt.slice(0, 80)) + '" src="' + texto(imagen.miniatura) + '">' +
                '<figcaption><span>' + texto(imagen.ancho + '×' + imagen.alto) + '</span>' +
                '<span>' + texto(imagen.proveedor.slice(0, 4)) + '</span></figcaption>';
            figura.addEventListener('click', function () { verDetalle(imagen); });
            historial.appendChild(figura);
        });
        historialVacio.hidden = historial.children.length > 0;
        btnMas.hidden = historial.children.length >= estado.total;
    }

    function cargarHistorial(reiniciar) {
        if (reiniciar) { estado.desde = 0; }
        // Cada petición lleva número: si llega una respuesta vieja, se descarta.
        var turno = ++estado.peticion;
        peticion('historial', {
            limite: estado.limite,
            desde: estado.desde,
            buscar: estado.busqueda
        }, 'GET').then(function (json) {
            if (turno !== estado.peticion) { return; }
            estado.total = json.total;
            estado.desde += json.imagenes.length;
            pintarHistorial(json.imagenes, reiniciar);
        }).catch(function (error) {
            if (turno !== estado.peticion) { return; }
            aviso(error.message, 'error');
        });
    }

    function recargarHistorial() {
        cargarHistorial(true);
    }

    btnMas.addEventListener('click', function () { cargarHistorial(false); });

    var temporizador = null;
    buscar.addEventListener('input', function () {
        window.clearTimeout(temporizador);
        temporizador = window.setTimeout(function () {
            estado.busqueda = buscar.value.trim();
            cargarHistorial(true);
        }, 320);
    });

    // --- Presets -----------------------------------------------------------
    function pintarPresets(presets) {
        listaPresets.innerHTML = '';
        if (!presets.length) {
            listaPresets.innerHTML = '<p class="ayuda">Aún no has guardado ningún preset.</p>';
            return;
        }
        presets.forEach(function (preset) {
            var chip = document.createElement('span');
            chip.className = 'preset';
            var detalle = preset.type === 'tamano'
                ? (preset.data.ancho + '×' + preset.data.alto)
                : 'prompt';
            chip.innerHTML =
                '<button type="button" class="aplicar">' + texto(preset.name) + ' · ' + texto(detalle) + '</button>' +
                '<button type="button" class="quitar" title="Eliminar preset">×</button>';
            chip.querySelector('.aplicar').addEventListener('click', function () {
                aplicarPreset(preset);
            });
            chip.querySelector('.quitar').addEventListener('click', function () {
                peticion('preset_eliminar', { id: preset.id }).then(function () {
                    chip.remove();
                    if (!listaPresets.children.length) { pintarPresets([]); }
                }).catch(function (error) { aviso(error.message, 'error'); });
            });
            listaPresets.appendChild(chip);
        });
    }

    function aplicarPreset(preset) {
        if (preset.type === 'tamano') {
            ancho.value = preset.data.ancho;
            alto.value = preset.data.alto;
            marcarProporcionLibre();
        } else {
            document.getElementById('prompt').value = preset.data.prompt || '';
            document.getElementById('negativo').value = preset.data.negativo || '';
            document.getElementById('realismo').checked = !!preset.data.realismo;
        }
        aviso('Preset «' + preset.name + '» aplicado.', 'ok');
    }

    document.getElementById('guardar-preset-prompt').addEventListener('click', function () {
        var prompt = document.getElementById('prompt').value.trim();
        if (!prompt) {
            aviso('Escribe un prompt antes de guardarlo como preset.', 'error');
            return;
        }
        var nombre = window.prompt('Nombre del preset de prompt:');
        if (!nombre) { return; }
        peticion('preset_guardar', {
            tipo: 'prompt',
            nombre: nombre,
            prompt: prompt,
            negativo: document.getElementById('negativo').value.trim(),
            realismo: document.getElementById('realismo').checked ? '1' : '0'
        }).then(function (json) {
            pintarPresets(json.presets);
            aviso('Preset guardado.', 'ok');
        }).catch(function (error) { aviso(error.message, 'error'); });
    });

    document.getElementById('guardar-preset-tamano').addEventListener('click', function () {
        var nombre = window.prompt('Nombre del preset de tamaño:');
        if (!nombre) { return; }
        peticion('preset_guardar', {
            tipo: 'tamano',
            nombre: nombre,
            ancho: ancho.value,
            alto: alto.value
        }).then(function (json) {
            pintarPresets(json.presets);
            aviso('Preset guardado.', 'ok');
        }).catch(function (error) { aviso(error.message, 'error'); });
    });

    // --- Arranque ----------------------------------------------------------
    cargarHistorial(true);
    peticion('presets', null, 'GET').then(function (json) {
        pintarPresets(json.presets);
    }).catch(function () { /* silencioso: los presets no son críticos */ });
})();
