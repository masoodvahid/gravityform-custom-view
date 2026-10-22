/* Bulk user SMS from one Gravity Forms custom-view page.
 * Preview and processing both happen on the server; the client never
 * decides the recipient list or the token values used in actual sends. */
(function($){
'use strict';
$(function(){
    $('.gfcv-container').each(function(){
        var $view=$(this);
        var $open=$view.find('.gfcv-bulk-open');
        if(!$open.length){return;}
        var viewId=$view.data('view-id');
        var $modal=$('.gfcv-bulk-modal').filter(function(){
            return String($(this).data('view-id'))===String(viewId);
        }).first();
        if(!$modal.length){return;}
        var $checks=$view.find('.gfcv-bulk-entry');
        var $all=$view.find('.gfcv-bulk-select-all');
        var $consent=$modal.find('.gfcv-bulk-consent');
        var $submit=$modal.find('.gfcv-bulk-submit');
        var $progress=$modal.find('.gfcv-bulk-progress');
        var $results=$modal.find('.gfcv-bulk-results');
        var ticket='', previewData=null, sending=false, cursor=0, restoreFocus=null;
        var nonce=(window.gfcv_vars&&gfcv_vars.bulk_sms_nonce)||'';

        function selectedIds(){
            return $checks.filter(':checked').map(function(){return $(this).val();}).get();
        }
        function selectionChanged(){
            var count=selectedIds().length,total=$checks.length;
            $all.prop('checked',total>0&&count===total);
            $all.prop('indeterminate',count>0&&count<total);
            $open.prop('disabled',count===0);
            $view.find('.gfcv-bulk-selection-status').text(
                count===0?'هیچ رکوردی انتخاب نشده است':count.toLocaleString('fa-IR')+' رکورد انتخاب شده'
            );
        }
        $checks.on('change',selectionChanged);
        $all.on('change',function(){
            $checks.prop('checked',!!$(this).prop('checked'));
            selectionChanged();
        });
        selectionChanged();

        function showModal(){
            restoreFocus=document.activeElement;
            $modal.addClass('is-open').show().attr('aria-hidden','false');
            $('body').addClass('gfcv-bulk-modal-active');
            $modal.find('.gfcv-bulk-close').trigger('focus');
        }
        function hideModal(){
            if(sending){return;}
            $modal.removeClass('is-open').hide().attr('aria-hidden','true');
            $('body').removeClass('gfcv-bulk-modal-active');
            if(restoreFocus&&typeof restoreFocus.focus==='function'){restoreFocus.focus();}
            restoreFocus=null;
        }
        $modal.find('.gfcv-bulk-close,.gfcv-bulk-cancel').on('click',hideModal);
        $modal.on('click',function(event){
            if(event.target===this){hideModal();}
        });
        $modal.on('keydown',function(event){
            if(event.key==='Escape'){
                event.preventDefault();hideModal();
            }
            if(event.key!=='Tab'){return;}
            var $focusable=$modal.find('button:not(:disabled),input:not(:disabled),a[href],summary')
                .filter(':visible');
            if(!$focusable.length){event.preventDefault();return;}
            var first=$focusable[0],last=$focusable[$focusable.length-1];
            if(event.shiftKey&&document.activeElement===first){
                event.preventDefault();last.focus();
            }else if(!event.shiftKey&&document.activeElement===last){
                event.preventDefault();first.focus();
            }
        });

        function resetModal(){
            sending=false;ticket='';cursor=0;previewData=null;
            $modal.find('.gfcv-bulk-count').text('در حال بررسی رکوردها...');
            $modal.find('.gfcv-bulk-checks').empty();
            $modal.find('.gfcv-bulk-sample-wrap').prop('hidden',true);
            $modal.find('.gfcv-bulk-invalid').prop('hidden',true).empty();
            $modal.find('.gfcv-bulk-report-link').prop('hidden',true);
            $results.empty();$progress.empty();
            $consent.prop('checked',false).prop('disabled',true);
            $submit.prop('disabled',true).text('تأیید و ارسال');
        }
        $consent.on('change',function(){
            $submit.prop('disabled',!previewData||!ticket||!$consent.prop('checked')||sending);
        });
        $open.on('click',function(){
            var ids=selectedIds();
            if(!ids.length||ids.length>100||!nonce){return;}
            resetModal();showModal();
            $.ajax({
                url:gfcv_vars.ajax_url,type:'POST',dataType:'json',
                data:{
                    action:'gfcv_bulk_user_sms_preview',nonce:nonce,
                    view_id:viewId,entry_ids:ids
                }
            }).done(function(response){
                if(!response||!response.success){
                    $modal.find('.gfcv-bulk-count').text(
                        response&&response.data&&response.data.message||'پیش‌نمایش انجام نشد.'
                    );return;
                }
                var data=response.data;
                previewData=data;ticket=data.ticket;
                $modal.find('.gfcv-bulk-count').text(
                    data.selected.toLocaleString('fa-IR')+' نفر برای ارسال پیام انتخاب شده‌اند.'
                );
                $modal.find('.gfcv-bulk-checks').text(
                    data.eligible.toLocaleString('fa-IR')+' گیرنده معتبر — '+
                    data.skipped.length.toLocaleString('fa-IR')+' رکورد کنار گذاشته شد.'
                );
                var $sampleWrap=$modal.find('.gfcv-bulk-sample-wrap').prop('hidden',false);
                $sampleWrap.find('.gfcv-bulk-sample-meta').text(
                    'نمونه از رکورد '+data.sample_entry_id+' | گیرنده '+data.first_mobile+
                    ' | پترن '+data.template
                );
                $sampleWrap.find('.gfcv-bulk-sample').text(data.sample);
                $sampleWrap.find('.gfcv-bulk-params pre').text(JSON.stringify(data.sample_params,null,2));
                $sampleWrap.find('.gfcv-bulk-template-note').prop('hidden',!!data.exact_preview);
                if(data.skipped.length){
                    var $skips=$modal.find('.gfcv-bulk-invalid').prop('hidden',false);
                    $skips.append($('<strong/>').text('رکوردهای کنارگذاشته‌شده:'));
                    var $ul=$('<ul/>').appendTo($skips);
                    data.skipped.forEach(function(item){
                        $ul.append($('<li/>').text('شناسه '+item.entry_id+' — '+item.reason));
                    });
                }
                $consent.prop('disabled',false);
                $progress.text('برای ارسال، عبارت تأیید را علامت بزنید و روی «تأیید و ارسال» کلیک کنید.');
            }).fail(function(xhr){
                $modal.find('.gfcv-bulk-count').text(
                    xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message||
                    'ارتباط با سرور برای پیش‌نمایش برقرار نشد (HTTP '+xhr.status+').'
                );
            });
        });

        function report(stats){
            var total=stats.eligible,processed=stats.processed;
            $progress.text(
                'پردازش '+processed.toLocaleString('fa-IR')+' از '+total.toLocaleString('fa-IR')+
                ' — پذیرفته‌شده: '+stats.accepted.toLocaleString('fa-IR')+
                ' — ناموفق: '+stats.failed.toLocaleString('fa-IR')+
                (stats.unknown?' — وضعیت نامشخص: '+stats.unknown.toLocaleString('fa-IR'):'')
            );
            $results.empty();
            (stats.results||[]).forEach(function(r){
                $results.append($('<div class="gfcv-bulk-result-row"/>')
                    .text('رکورد '+r.entry_id+' — '+r.mobile+' — '+r.message)
                    .attr('data-status',r.status));
            });
            if(stats.complete){
                sending=false;
                $submit.text('ارسال پایان یافت').prop('disabled',true);
                $modal.find('.gfcv-bulk-close,.gfcv-bulk-cancel').prop('disabled',false);
                $modal.find('.gfcv-bulk-report-link').prop('hidden',false);
                selectionChanged();
            }
        }
        function sendNext(retried){
            $.ajax({
                url:gfcv_vars.ajax_url,type:'POST',dataType:'json',
                data:{
                    action:'gfcv_bulk_user_sms_step',nonce:nonce,ticket:ticket,
                    cursor:cursor,confirmed:'1'
                }
            }).done(function(response){
                if(!response||!response.success){
                    fail(response&&response.data&&response.data.message||'ارسال ادامه پیدا نکرد.');
                    return;
                }
                var stats=response.data;
                report(stats);
                cursor=stats.next;
                if(!stats.complete){sendNext(false);}
            }).fail(function(xhr){
                // If the network response was lost, replay the same cursor:
                // the server has already claimed it before calling Kavenegar.
                if(!retried&&xhr.status!==400&&xhr.status!==403&&xhr.status!==410){
                    $progress.text('در حال بازیابی نتیجه مرحله قبل بدون ارسال تکراری...');
                    sendNext(true);
                    return;
                }
                fail(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message||
                    'ارتباط قطع شد (HTTP '+xhr.status+'). برای جلوگیری از پیامک تکراری، ادامه خودکار متوقف شد. وضعیت را در گزارش پیامک‌ها بررسی کنید.');
            });
        }
        function fail(message){
            sending=false;
            $progress.text(message);
            $submit.prop('disabled',true).text('ارسال متوقف شد');
            $modal.find('.gfcv-bulk-close,.gfcv-bulk-cancel').prop('disabled',false);
            $modal.find('.gfcv-bulk-report-link').prop('hidden',false);
        }
        $submit.on('click',function(){
            if(sending||!ticket||!$consent.prop('checked')||!previewData){return;}
            sending=true;cursor=0;
            $submit.prop('disabled',true).text('در حال ارسال...');
            $consent.prop('disabled',true);
            $modal.find('.gfcv-bulk-close,.gfcv-bulk-cancel').prop('disabled',true);
            $progress.text('ارسال پیامک‌ها آغاز شد؛ تا پایان عملیات این صفحه را باز نگه دارید.');
            sendNext(false);
        });
    });
});
})(jQuery);
