
function getBaseUrl() {
  const { protocol, host, hostname, pathname } = window.location;
  const isLocal = (hostname === 'localhost' || hostname === '127.0.0.1');

  // Base inicial con el protocolo real de la página
  let origin = protocol + '//' + host;

  // Forzar https en producción (tu dominio)
  const PROD_HOSTS = ['refaccionariarivera.com', 'www.refaccionariarivera.com'];
  if (PROD_HOSTS.includes(hostname) && protocol !== 'https:') {
    origin = 'https://' + host;
  }

  // En local: añade la carpeta del proyecto si aplica
  if (isLocal) {
    const PROJECT = 'PosVentasPOO';
    const hasProject = new RegExp(`^/${PROJECT}(?:/|$)`, 'i').test(pathname);
    const baseFolder = hasProject ? `/${PROJECT}` : '';
    origin = protocol + '//' + host + baseFolder;
  }

  return origin.replace(/\/+$/,''); // sin slash final
}

const BASE_URL = getBaseUrl();

$(document).ready(function () {
  $('#formLogin').on('submit', function (e) {
    e.preventDefault();
    $('#mensaje-error').hide().text('');

    $.ajax({
      url: BASE_URL + '/controllers/LoginController.php',
      type: 'POST',
      data: $(this).serialize(),
      success: function (response) {
        if ((response || '').trim() === 'ok') {
          window.location.href = BASE_URL + '/views/private/inicio/index.php';
        } else {
          $('#mensaje-error').text(response).fadeIn();
        }
      },
      error: function () {
        $('#mensaje-error').text('Error del servidor. Intenta más tarde.').fadeIn();
      }
    });
  });
});

