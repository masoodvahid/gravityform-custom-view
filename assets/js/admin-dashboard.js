/* Admin overview: accessible search, copy, and Kavenegar outbox. */
(function($) {
'use strict';
$(function() {
    var $page=$('.gfcv-dashboard');
    if(!$page.length) return;
    var $rows=$page.find('.gfcv-dashboard-view-row');
    var $search=$('#gfcv-view-search');
    var filter='all';
    var normalise=function(value) {
        return String(value||'').toLocaleLowerCase('fa')
            .replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').replace(/\s+/g,' ').trim();
    };
    function filterRows() {
        var term=normalise($search.val()),visible=0;
        $rows.each(function(){
            var $row=$(this);
            var matchTerm=normalise($row.attr('data-search')).indexOf(term)!==-1;
            var matchFilter=filter==='all' ||
                (filter==='sms' && $row.attr('data-has-sms')==='1') ||
                (filter==='restricted' && $row.attr('data-is-restricted')==='1');
            var show=matchTerm&&matchFilter;
            $row.toggle(show);
            if(show)visible++;
        });
        $('#gfcv-visible-count').text(visible.toLocaleString('fa-IR'));
        $('#gfcv-filter-empty').prop('hidden',visible!==0);
        $page.find('.gfcv-dashboard-table-scroll').prop('hidden',visible===0);
    }
    $search.on('input',filterRows);
    $page.find('.gfcv-filter-button').on('click',function(){
        filter=$(this).data('filter');
        $page.find('.gfcv-filter-button').removeClass('is-active').attr('aria-pressed','false');
        $(this).addClass('is-active').attr('aria-pressed','true');
        filterRows();
    });
    $('#gfcv-clear-filters').on('click',function(){
        $search.val('');filter='all';
        $page.find('.gfcv-filter-button').removeClass('is-active').attr('aria-pressed','false');
        $page.find('.gfcv-filter-button[data-filter="all"]').addClass('is-active').attr('aria-pressed','true');
        filterRows();
        $search.trigger('focus');
    });
    $page.on('submit','.gfcv-delete-view-form',function(event){
        var question=$(this).attr('data-confirm')||'آیا از حذف لیست اطمینان دارید؟';
        if(!window.confirm(question))event.preventDefault();
    });

    function showFeedback(message) {
        $('#gfcv-dashboard-feedback').text(message);
    }
    $page.on('click','.gfcv-shortcode-copy',function() {
        var $button=$(this),value=String($button.attr('data-shortcode')||'');
        if(!value)return;
        function success(){
            $button.find('.gfcv-copy-text').text('کپی شد');
            showFeedback('کد کوتاه در کلیپ‌بورد کپی شد.');
            $button.trigger('focus');
        }
        function fallback(){
            var $temp=$('<textarea readonly/>').val(value).css({position:'fixed',left:'-2000px'});
            $('body').append($temp);
            $temp[0].select();
            var ok=false;
            try {ok=document.execCommand('copy');}catch(e){}
            $temp.remove();
            if(ok)success();else showFeedback('امکان کپی خودکار نبود؛ کد کوتاه را به‌صورت دستی انتخاب کنید.');
        }
        if(navigator.clipboard&&navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(success,fallback);
        } else fallback();
    });

    var $outbox=$('#gfcv-dashboard-outbox'),$button=$('#gfcv-show-outbox'),
        $status=$('#gfcv-outbox-status'),$table=$('#gfcv-outbox-table'),
        $tbody=$('#gfcv-outbox-rows'),$pages=$('#gfcv-outbox-pagination');
    var page=1,pageSize=15,logs=[];
    function toggleOutbox(show){
        $outbox.prop('hidden',!show);
        $button.attr('aria-expanded',show?'true':'false');
        if(show){
            $outbox[0].scrollIntoView({behavior:'smooth',block:'start'});
        }else{
            $button.trigger('focus');
        }
    }
    function safeCell(text,cls){
        return $('<td/>').addClass(cls||'').text(text===null||text===undefined||text===''?'—':text);
    }
    function renderPage(){
        $tbody.empty();
        var maxPages=Math.max(1,Math.ceil(logs.length/pageSize));
        page=Math.max(1,Math.min(page,maxPages));
        logs.slice((page-1)*pageSize,page*pageSize).forEach(function(r) {
            var $tr=$('<tr/>');
            $tr.append(safeCell(r.id)).append(safeCell(r.date))
                .append(safeCell(r.receptor)).append(safeCell(r.sender))
                .append(safeCell(r.message,'gfcv-outbox-message')).append(safeCell(r.status));
            $tbody.append($tr);
        });
        $table.prop('hidden',logs.length===0);
        $pages.prop('hidden',logs.length<=pageSize);
        $('#gfcv-outbox-page-label').text('صفحه '+page+' از '+maxPages+' — '+logs.length+' پیام');
        $('#gfcv-outbox-prev').prop('disabled',page<=1);
        $('#gfcv-outbox-next').prop('disabled',page>=maxPages);
    }
    function fetchOutbox(){
        var config=window.gfcvDashboardData||{};
        if(!config.nonce){$status.text('کلید امنیتی گزارش در دسترس نیست.');return;}
        var mode=$('#gfcv-outbox-mode').val();
        var from=$('#gfcv-outbox-from').val(),to=$('#gfcv-outbox-to').val();
        if(mode==='range'&&(!from||!to)){ $status.text('تاریخ شروع و پایان را وارد کنید.');return;}
        var $fetch=$('#gfcv-fetch-outbox').prop('disabled',true);
        $status.text('در حال دریافت گزارش از کاوه‌نگار...');
        $.post(config.ajaxUrl||ajaxurl,{
            action:'gfcv_kavenegar_outbox',nonce:config.nonce,
            mode:mode,from:from,to:to
        }).done(function(resp){
            if(!resp||!resp.success){
                logs=[];renderPage();
                $status.text(resp&&resp.data&&resp.data.message||'گزارش در دسترس نیست.');return;
            }
            logs=Array.isArray(resp.data.rows)?resp.data.rows:[];page=1;
            renderPage();
            $status.text((resp.data.description||'')+' — '+logs.length+' مورد دریافت شد.'+
                (resp.data.limited?' حداکثر ۳۰۰۰ مورد نمایش داده می‌شود.':'')+
                (!logs.length?' موردی برای نمایش وجود ندارد.':''));
        }).fail(function(xhr){
            logs=[];renderPage();
            $status.text(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message||
                'خطا در ارتباط با سرویس پیامک. اتصال سرور به کاوه‌نگار را بررسی کنید.');
        }).always(function(){ $fetch.prop('disabled',false); });
    }
    $button.on('click',function(){toggleOutbox(!$outbox.prop('hidden'));if(!$outbox.prop('hidden')&&!logs.length)fetchOutbox();});
    $('#gfcv-close-outbox').on('click',function(){toggleOutbox(false);});
    $('#gfcv-outbox-filters').on('submit',function(e){e.preventDefault();fetchOutbox();});
    $('#gfcv-outbox-mode').on('change',function(){
        $('.gfcv-outbox-range-field').prop('hidden',$(this).val()!=='range');
    });
    $('#gfcv-outbox-prev').on('click',function(){page--;renderPage();});
    $('#gfcv-outbox-next').on('click',function(){page++;renderPage();});
    if(window.location.hash==='#gfcv-service-settings') {
        $('#gfcv-service-settings').prop('open',true);
    }
});
})(jQuery);
