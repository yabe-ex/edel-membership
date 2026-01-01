jQuery(document).ready(function ($) {
    // -----------------------------------------------------------------
    // 1. パスワード表示切り替え
    // -----------------------------------------------------------------
    $('.edel-password-toggle').on('click', function () {
        var $wrapper = $(this).closest('.edel-password-wrapper');
        var $input = $wrapper.find('input');
        var $iconEye = $(this).find('.icon-eye');
        var $iconOff = $(this).find('.icon-eye-off');

        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $iconEye.hide();
            $iconOff.show();
        } else {
            $input.attr('type', 'password');
            $iconEye.show();
            $iconOff.hide();
        }
    });

    // -----------------------------------------------------------------
    // 2. パスワード強度メーター
    // -----------------------------------------------------------------
    $('.edel-input-password').on('input', function () {
        var $field = $(this).closest('.edel-field');
        var $meter = $field.find('.edel-password-meter');
        if ($meter.length === 0) return;

        var password = $(this).val();
        var $bar = $meter.find('.meter-bar');
        var $text = $meter.find('.meter-text');

        var strength = 0;
        if (password.length >= 8) strength += 1;
        if (password.length >= 12) strength += 1;
        if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 1;
        if (password.match(/([a-zA-Z])/) && password.match(/([0-9])/)) strength += 1;
        if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) strength += 1;

        $bar.removeClass('weak medium strong');

        if (password.length === 0) {
            $bar.css('width', '0%');
            $text.text('');
        } else if (password.length < 8) {
            $bar.css('width', '30%').addClass('weak');
            $text.text('短すぎます (8文字以上)');
            $text.css('color', '#e53e3e');
        } else if (strength < 3) {
            $bar.css('width', '60%').addClass('medium');
            $text.text('普通');
            $text.css('color', '#d69e2e');
        } else {
            $bar.css('width', '100%').addClass('strong');
            $text.text('強力');
            $text.css('color', '#2f855a');
        }
    });

    // -----------------------------------------------------------------
    // 3. reCAPTCHA
    // -----------------------------------------------------------------
    if (typeof edel_membership_vars !== 'undefined') {
        if (typeof grecaptcha === 'undefined' && edel_membership_vars.recaptcha_enabled) {
            console.warn('Edel Warning: reCAPTCHA is enabled but API not loaded yet.');
        }

        if (edel_membership_vars.recaptcha_enabled) {
            grecaptcha.ready(function () {
                $('.edel-form').on('submit', function (e) {
                    if ($(this).find('.edel-recaptcha-response').val()) return;
                    e.preventDefault();
                    var form = $(this);
                    try {
                        grecaptcha.execute(edel_membership_vars.site_key, { action: 'submit' }).then(function (token) {
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
        }
    }

    // -----------------------------------------------------------------
    // 4. 通知メッセージ処理 (更新版)
    // -----------------------------------------------------------------
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('edel_status');

    if (status) {
        let msg = '';
        if (status === 'registered') {
            msg = '会員登録が完了しました。';
        } else if (status === 'registered_login') {
            msg = '会員登録が完了し、ログインしました。';
        } else if (status === 'logged_in') {
            msg = 'ログインしました。';
        }

        if (msg) {
            showEdelNotification(msg);
            const newUrl = window.location.pathname + window.location.hash;
            window.history.replaceState(null, '', newUrl);
        }
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
