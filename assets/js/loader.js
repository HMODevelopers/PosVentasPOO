$.ajaxSetup({
    beforeSend: function () {
        ShowLoading();
    },
    complete: function () {
        HideLoading();
    }
});

// ✅ Muestra el loader, pero evita activarlo varias veces seguidas
var ShowLoading = function () {
    if (!$(".wrapper-loader").hasClass('show')) { 
        $(".wrapper-loader").show().addClass('show');
    }
};


var HideLoading = function () {
    setTimeout(function () {

        $(".wrapper-loader").removeClass('show')
        setTimeout(function () {
            $(".wrapper-loader").hide();
        }, 200);

    }, 400)
}