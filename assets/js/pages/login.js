/* login.js — versión mejorada (ACL + start_path por rol) */

(function () {
  "use strict";

  // ——————————————————————————————————
  // BASE URL
  // ——————————————————————————————————
  function getBaseUrl() {
    // 1) Si hay <base href="..."> úsalo
    const baseTag = document.querySelector('base[href]');
    if (baseTag) {
      const href = baseTag.getAttribute('href') || '';
      return href.replace(/\/+$/, '');
    }

    const { protocol, host, hostname, pathname } = window.location;
    const isLocal = (hostname === 'localhost' || hostname === '127.0.0.1');

    // 2) Origen por defecto
    let origin = protocol + '//' + host;

    // 3) HTTPS forzado para producción
    const PROD_HOSTS = ['refaccionariarivera.com', 'www.refaccionariarivera.com'];
    if (PROD_HOSTS.includes(hostname) && protocol !== 'https:') {
      origin = 'https://' + host;
    }

    // 4) Subcarpeta de proyecto (en local o si se despliega bajo subruta)
    const PROJECT_CANDIDATES = ['PosVentasPOO', 'REFASOFT-V4', 'refasoft', 'pos'];
    let subdir = '';

    for (const name of PROJECT_CANDIDATES) {
      const re = new RegExp(`^/${name}(?:/|$)`, 'i');
      if (re.test(pathname)) {
        subdir = `/${name}`;
        break;
      }
    }

    if (isLocal && !subdir) {
      const PROJECT = 'PosVentasPOO';
      const hasProject = new RegExp(`^/${PROJECT}(?:/|$)`, 'i').test(pathname);
      subdir = hasProject ? `/${PROJECT}` : '';
    }

    return (origin + subdir).replace(/\/+$/, '');
  }

  // Exponer global por si lo necesitas en otros scripts
  window.BASE_URL = getBaseUrl();

  // ——————————————————————————————————
  // Helpers UI
  // ——————————————————————————————————
  function setLoading($btn, isLoading) {
    if (!$btn || !$btn.length) return;
    if (isLoading) {
      $btn.data('prev-text', $btn.html());
      $btn.prop('disabled', true).html('Entrando…');
    } else {
      $btn.prop('disabled', false).html($btn.data('prev-text') || 'Iniciar sesión');
    }
  }

  function showError(msg) {
    $('#mensaje-error').text(msg || 'Error desconocido').stop(true, true).fadeIn();
  }

  function clearError() {
    $('#mensaje-error').hide().text('');
  }

  // CAMBIO: sanitizamos y normalizamos la ruta antes de redirigir
  function safeRedirect(relativePath) {
    let rel = (relativePath || '/views/private/inicio/index.php').trim();
    // Evita URLs absolutas externas: solo permitimos rutas relativas del sitio
    if (/^https?:\/\//i.test(rel)) rel = '/views/private/inicio/index.php';
    if (!rel.startsWith('/')) rel = '/' + rel;
    window.location.href = window.BASE_URL + rel;
  }

  // ——————————————————————————————————
  // Ready
  // ——————————————————————————————————
  $(document).ready(function () {
    const $form = $('#formLogin');
    const $submit = $form.find('[type="submit"]');

    $form.on('submit', function (e) {
      e.preventDefault();
      clearError();

      // CAMBIO: evita doble submit si ya está cargando
      if ($submit.prop('disabled')) return;

      setLoading($submit, true);

      $.ajax({
        url: window.BASE_URL + '/controllers/LoginController.php',
        type: 'POST',
        data: $form.serialize(),
        dataType: 'text',            // el backend responde texto plano: "ok|/ruta" o mensaje
        timeout: 15000,              // 15s
        cache: false,
        success: function (response) {
          // CAMBIO: eliminamos BOM y normalizamos
          const txt = String(response || '').replace(/^\uFEFF/, '').trim();

          // CAMBIO: regex tolerante a espacios: "ok", "OK", "ok|/ruta", "ok | /ruta"
          const m = txt.match(/^\s*ok\s*(?:\|\s*(.*))?$/i);
          if (m) {
            const rel = (m[1] || '/views/private/inicio/index.php').trim();
            safeRedirect(rel);
            return;
          }

          // Fallback: si algún día regresas JSON { ok:true, redirect:"/ruta" }
          if ((txt.startsWith('{') && txt.endsWith('}')) || (txt.startsWith('[') && txt.endsWith(']'))) {
            try {
              const json = JSON.parse(txt);
              if (json && json.ok) {
                safeRedirect(json.redirect || '/views/private/inicio/index.php');
                return;
              }
              showError(json.msg || 'No fue posible iniciar sesión.');
              $form.find('input[type="password"]').val('');
              setLoading($submit, false);
              return;
            } catch (_) {
              // Si no se puede parsear, continúa con manejo de texto
            }
          }

          // Cualquier otro texto es un mensaje de error del servidor
          showError(txt || 'Credenciales inválidas.');
          $form.find('input[type="password"]').val('');
          setLoading($submit, false);
        },
        error: function (xhr, status) {
          if (status === 'timeout') {
            showError('El servidor tardó demasiado en responder. Intenta de nuevo.');
          } else {
            showError('Error del servidor. Intenta más tarde.');
          }
          $form.find('input[type="password"]').val('');
          setLoading($submit, false);
        }
      });
    });
  });
})();
