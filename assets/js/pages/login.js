function getBaseUrl() {
    var host = window.location.hostname;
    
    if (host === 'localhost' || host === '127.0.0.1') {
        return 'http://localhost/PosVentasPOO'; 
    } else {
        return 'http://refaccionariarivera.com'; 
    }
}

const BASE_URL = getBaseUrl(); // ✅ aquí asignas el valor

$(document).ready(function () {
    $('#formLogin').on('submit', function (e) {
        e.preventDefault();
        $('#mensaje-error').hide().text('');

        $.ajax({
            url: BASE_URL + '/controllers/LoginController.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                if (response.trim() === 'ok') {
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
