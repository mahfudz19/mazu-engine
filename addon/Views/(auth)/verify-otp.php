<?php

/**
 * @var \App\Core\View\PageMeta $meta
 * @var string $email Email user yang akan diverifikasi
 * @var string|null $error Error message (jika ada)
 * @var string|null $success Success message (jika ada)
 */
?>

<div class="otp-verification-container">
    <div class="otp-header">
        <div class="otp-icon">🔐</div>
        <h1 class="otp-title">Verifikasi Email</h1>
        <p class="otp-description">
            Kami telah mengirim kode 6-digit ke<br>
            <strong><?= htmlspecialchars($email ?? '') ?></strong>
        </p>
    </div>

    <?php if (isset($error)): ?>
        <div class="otp-error" role="alert">
            <span class="otp-error-icon">⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="otp-success" role="alert">
            <span class="otp-success-icon">✅</span>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="/verify-otp" id="otp-form">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email ?? '') ?>">

        <div class="otp-inputs" role="group" aria-label="Kode verifikasi 6 digit">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="one-time-code"
                aria-label="Digit pertama"
                required
                class="otp-digit"
                data-index="0">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Digit kedua"
                required
                class="otp-digit"
                data-index="1">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Digit ketiga"
                required
                class="otp-digit"
                data-index="2">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Digit keempat"
                required
                class="otp-digit"
                data-index="3">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Digit kelima"
                required
                class="otp-digit"
                data-index="4">
            <input
                type="text"
                maxlength="1"
                pattern="[0-9]"
                inputmode="numeric"
                autocomplete="off"
                aria-label="Digit keenam"
                required
                class="otp-digit"
                data-index="5">
        </div>

        <input type="hidden" name="otp_code" id="otp-code-hidden" required>

        <button type="submit" class="otp-button" id="verify-button" disabled>
            <span class="button-text">Verifikasi</span>
            <span class="button-loading" style="display: none;">
                <span class="spinner"></span>
                Memverifikasi...
            </span>
        </button>
    </form>

    <div class="otp-footer">
        <div class="otp-timer" id="otp-timer">
            <span class="timer-icon">⏱️</span>
            <span class="timer-text" id="timer-text">Kode berlaku 15:00</span>
        </div>

        <button
            type="button"
            class="otp-resend"
            id="resend-button"
            disabled
            data-spa>
            <span class="resend-text">Kirim Ulang OTP</span>
            <span class="resend-countdown">(60s)</span>
        </button>

        <a href="/register" class="otp-back-link" data-spa>
            ← Kembali ke Register
        </a>
    </div>
</div>

<script>
    (function() {
        const inputs = document.querySelectorAll('.otp-digit');
        const form = document.getElementById('otp-form');
        const verifyButton = document.getElementById('verify-button');
        const otpHidden = document.getElementById('otp-code-hidden');
        const timerText = document.getElementById('timer-text');
        const resendButton = document.getElementById('resend-button');
        const resendCountdown = resendButton.querySelector('.resend-countdown');

        let timeLeft = 900; // 15 minutes
        let resendCooldown = 60; // 60 seconds

        // Auto-focus first input
        inputs[0].focus();

        // Handle input
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;

                // Only allow numbers
                if (!/^\d*$/.test(value)) {
                    e.target.value = '';
                    return;
                }

                // Auto-focus next
                if (value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }

                // Check if all filled
                checkAllFilled();
            });

            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && input.value === '' && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasted = e.clipboardData.getData('text').slice(0, 6);

                if (/^\d{6}$/.test(pasted)) {
                    inputs.forEach((inp, i) => {
                        inp.value = pasted[i];
                        if (i < 5) inputs[i + 1].focus();
                    });
                    checkAllFilled();
                }
            });
        });

        function checkAllFilled() {
            const allFilled = Array.from(inputs).every(i => i.value.length === 1);
            if (allFilled) {
                verifyButton.disabled = false;
                otpHidden.value = Array.from(inputs).map(i => i.value).join('');
            } else {
                verifyButton.disabled = true;
                otpHidden.value = '';
            }
        }

        // Handle form submit
        form.addEventListener('submit', (e) => {
            verifyButton.disabled = true;
            verifyButton.querySelector('.button-text').style.display = 'none';
            verifyButton.querySelector('.button-loading').style.display = 'inline-flex';
        });

        // Timer countdown
        function startTimer() {
            const interval = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    timerText.textContent = 'Kode telah kedaluwarsa';
                    timerText.closest('.otp-timer').classList.add('expired');
                    return;
                }

                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerText.textContent = `Kode berlaku ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                if (timeLeft < 60) {
                    timerText.closest('.otp-timer').classList.add('urgent');
                }
            }, 1000);
        }

        // Resend cooldown
        function startResendCooldown() {
            resendButton.disabled = true;
            let cooldown = resendCooldown;

            const interval = setInterval(() => {
                if (cooldown <= 0) {
                    clearInterval(interval);
                    resendButton.disabled = false;
                    resendCountdown.textContent = '';
                    return;
                }

                cooldown--;
                resendCountdown.textContent = `(${cooldown}s)`;
            }, 1000);
        }

        // Handle resend click
        resendButton.addEventListener('click', () => {
            if (!resendButton.disabled) {
                window.location.href = '/resend-otp?email=' + encodeURIComponent('<?= htmlspecialchars($email ?? '') ?>');
            }
        });

        // Start timers
        startTimer();
        startResendCooldown();
    })();
</script>

<style>
    .otp-verification-container {
        max-width: 420px;
        margin: 0 auto;
        padding: 20px;
    }

    .otp-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .otp-icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .otp-title {
        font-size: 24px;
        font-weight: 700;
        color: #1a1a1a;
        margin: 0 0 10px 0;
    }

    .otp-description {
        color: #666;
        font-size: 14px;
        line-height: 1.6;
    }

    .otp-description strong {
        color: #333;
        word-break: break-all;
    }

    .otp-error,
    .otp-success {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }

    .otp-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #dc2626;
    }

    .otp-success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #059669;
    }

    .otp-inputs {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin: 30px 0;
    }

    .otp-digit {
        width: 50px;
        height: 60px;
        font-size: 24px;
        font-weight: 600;
        text-align: center;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        transition: all 0.2s;
        background: white;
    }

    .otp-digit:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .otp-digit.filled {
        border-color: #10b981;
        background: #ecfdf5;
    }

    .otp-button {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .otp-button:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    .otp-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .button-loading {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .spinner {
        width: 18px;
        height: 18px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .otp-footer {
        margin-top: 30px;
        text-align: center;
    }

    .otp-timer {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: #f8fafc;
        border-radius: 8px;
        font-size: 14px;
        color: #64748b;
        margin-bottom: 15px;
        transition: all 0.3s;
    }

    .otp-timer.urgent {
        background: #fef2f2;
        color: #dc2626;
        animation: pulse 1s infinite;
    }

    .otp-timer.expired {
        background: #fef2f2;
        color: #dc2626;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.6;
        }
    }

    .otp-resend {
        display: block;
        width: 100%;
        padding: 12px;
        background: none;
        border: 2px solid #667eea;
        color: #667eea;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        margin-bottom: 15px;
    }

    .otp-resend:hover:not(:disabled) {
        background: #667eea;
        color: white;
    }

    .otp-resend:disabled {
        border-color: #cbd5e1;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .otp-back-link {
        display: inline-block;
        color: #64748b;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s;
    }

    .otp-back-link:hover {
        color: #667eea;
    }
</style>