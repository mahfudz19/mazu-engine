<?php

/**
 * @var \App\Core\View\PageMeta $meta
 * @var string $email Email tujuan OTP
 */
?>

<div class="otp-sent-container">
    <div class="otp-sent-header">
        <div class="otp-sent-icon">📧</div>
        <h1 class="otp-sent-title">Email Terkirim!</h1>
        <p class="otp-sent-description">
            Kami telah mengirim kode verifikasi ke<br>
            <strong><?= htmlspecialchars($email ?? '') ?></strong>
        </p>
    </div>

    <div class="otp-sent-instructions">
        <div class="instruction-step">
            <span class="step-number">1</span>
            <span class="step-text">Buka inbox email Anda</span>
        </div>
        <div class="instruction-step">
            <span class="step-number">2</span>
            <span class="step-text">Cari email dari <?= htmlspecialchars(env('MAIL_FROM_NAME', 'Mazu Framework')) ?></span>
        </div>
        <div class="instruction-step">
            <span class="step-number">3</span>
            <span class="step-text">Salin kode 6 digit dari email</span>
        </div>
        <div class="instruction-step">
            <span class="step-number">4</span>
            <span class="step-text">Masukkan kode di halaman verifikasi</span>
        </div>
    </div>

    <div class="otp-sent-actions">
        <a
            href="/verify-otp?email=<?= urlencode($email ?? '') ?>"
            class="otp-sent-button primary"
            data-spa
        >
            Buka Halaman Verifikasi
        </a>

        <button
            type="button"
            class="otp-sent-button secondary"
            id="resend-from-sent"
            disabled>
            <span class="button-text">Kirim Ulang Email</span>
            <span class="button-countdown">(60s)</span>
        </button>
    </div>

    <div class="otp-sent-tips">
        <p class="tips-title">💡 Tips:</p>
        <ul class="tips-list">
            <li>Periksa folder spam/junk jika email tidak muncul di inbox</li>
            <li>Pastikan alamat email yang Anda masukkan sudah benar</li>
            <li>Kode verifikasi berlaku selama 15 menit</li>
        </ul>
    </div>

    <a href="/register" class="otp-sent-back" data-spa>
        ← Kembali ke Register
    </a>
</div>

<script>
    (function() {
        const resendButton = document.getElementById('resend-from-sent');
        const countdownSpan = resendButton.querySelector('.button-countdown');
        let cooldown = 60;

        function startCooldown() {
            const interval = setInterval(() => {
                if (cooldown <= 0) {
                    clearInterval(interval);
                    resendButton.disabled = false;
                    countdownSpan.textContent = '';
                    return;
                }

                cooldown--;
                countdownSpan.textContent = `(${cooldown}s)`;
            }, 1000);
        }

        resendButton.addEventListener('click', () => {
            if (!resendButton.disabled) {
                window.location.href = '/resend-otp?email=' + encodeURIComponent('<?= htmlspecialchars($email ?? '') ?>');
            }
        });

        startCooldown();
    })();
</script>

<style>
    .otp-sent-container {
        max-width: 420px;
        margin: 0 auto;
        padding: 20px;
    }

    .otp-sent-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .otp-sent-icon {
        font-size: 64px;
        margin-bottom: 15px;
        animation: bounce 1s ease infinite;
    }

    @keyframes bounce {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-10px);
        }
    }

    .otp-sent-title {
        font-size: 24px;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0 0 10px 0;
    }

    .otp-sent-description {
        color: #666;
        font-size: 14px;
        line-height: 1.6;
    }

    .otp-sent-description strong {
        color: #333;
        word-break: break-all;
    }

    .otp-sent-instructions {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .instruction-step {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .instruction-step:last-child {
        border-bottom: none;
    }

    .step-number {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 50%;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .step-text {
        color: #333;
        font-size: 14px;
    }

    .otp-sent-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 25px;
    }

    .otp-sent-button {
        width: 100%;
        padding: 16px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        text-align: center;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .otp-sent-button.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .otp-sent-button.primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    .otp-sent-button.secondary {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }

    .otp-sent-button.secondary:hover:not(:disabled) {
        background: #667eea;
        color: white;
    }

    .otp-sent-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .otp-sent-tips {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .tips-title {
        font-weight: 600;
        color: #92400e;
        margin: 0 0 10px 0;
        font-size: 14px;
    }

    .tips-list {
        margin: 0;
        padding-left: 20px;
        color: #78350f;
        font-size: 13px;
        line-height: 1.8;
    }

    .tips-list li {
        margin-bottom: 5px;
    }

    .otp-sent-back {
        display: inline-block;
        color: #64748b;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s;
    }

    .otp-sent-back:hover {
        color: #667eea;
    }
</style>