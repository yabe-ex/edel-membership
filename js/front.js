jQuery(document).ready(function ($) {
    console.log('Edel Membership: JS Loaded');

    // ★修正: 変数名を edel_membership_vars に変更
    if (typeof edel_membership_vars === 'undefined') {
        console.error('Edel Error: edel_membership_vars is undefined (Conflict check)');
        return;
    }
    console.log('Edel Membership: Vars', edel_membership_vars);

    if (typeof grecaptcha === 'undefined') {
        // API未ロード時はログだけ出す（OFF設定の可能性もあるためエラーにはしない）
        if (edel_membership_vars.recaptcha_enabled) {
            console.warn('Edel Warning: reCAPTCHA is enabled but API not loaded yet.');
        }
    }

    // ★修正: 変数名変更
    if (edel_membership_vars.recaptcha_enabled) {
        console.log('Edel: reCAPTCHA is ENABLED');

        grecaptcha.ready(function () {
            console.log('Edel: grecaptcha is READY');

            $('.edel-form').on('submit', function (e) {
                if ($(this).find('.edel-recaptcha-response').val()) {
                    return;
                }

                e.preventDefault();
                console.log('Edel: Requesting token...');

                var form = $(this);

                try {
                    // ★修正: 変数名変更
                    grecaptcha.execute(edel_membership_vars.site_key, { action: 'submit' }).then(function (token) {
                        console.log('Edel: Token received!', token);

                        if (!token) {
                            alert('reCAPTCHAエラー: トークンが取得できませんでした。');
                            return;
                        }

                        var hiddenField = form.find('.edel-recaptcha-response');
                        if (hiddenField.length > 0) {
                            hiddenField.val(token);
                            form.off('submit').submit();
                        }
                    });
                } catch (err) {
                    console.error('Edel Exception:', err);
                }
            });
        });
    } else {
        console.log('Edel: reCAPTCHA is DISABLED (by config)');
    }

    // --- 登録完了通知処理 ---
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('edel_status') === 'registered') {
        showEdelNotification('登録が完了しました。');
        const newUrl = window.location.pathname + window.location.hash;
        window.history.replaceState(null, '', newUrl);
    }

    function showEdelNotification(message) {
        var $notification = $('<div id="edel-notification">' + message + '</div>');
        $('body').append($notification);
        setTimeout(function () {
            $notification.addClass('show');
        }, 100);
        setTimeout(function () {
            $notification.removeClass('show');
            setTimeout(function () {
                $notification.remove();
            }, 500);
        }, 3000);
    }
});
