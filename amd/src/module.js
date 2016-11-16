
define(['jquery'], function($){

    function init(){

        $('.collapsable').each(function(col){
            var target = $(col).attr('target');
            $(target).hide();
        });

        $(document).ready(function(){
            $('.collapsable').click(function(e){
                e.preventDefault();
                var target = $(this).attr('target');
                $(target).slideToggle();
            });
        });
    }
    $(".ucicbootstrap .contentwithoutlink .text-center").addClass("activityinstancess");
    return {
        init: init,
    }


});