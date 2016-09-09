
define(['jquery'], function($){

    function init(){
        $(document).ready(function(){
            $('.collapsable').click(function(e){
                e.preventDefault();
                var target = $(this).attr('target');
                $(target).slideToggle();
            });
        });
    }

    return {
        init: init,
    }


});