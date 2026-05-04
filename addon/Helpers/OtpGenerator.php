<?php

namespace Addon\Helpers;

/**
 * OTP Generator Helper
 *
 * Menghasilkan dan memvalidasi kode OTP (One-Time Password).
 * OTP berupa 6 digit angka acak.
 */
class OtpGenerator
{
    /**
     * Generate OTP 6 digit
     *
     * @return string 6-digit numeric code
     */
    public static function generate(): string
    {
        $otp = '';
        for ($i = 0; $i < 6; $i++) {
            $otp .= random_int(0, 9);
        }

        return $otp;
    }

    /**
     * Generate OTP dengan format tertentu (misal: tanpa angka 0 di depan)
     *
     * @param bool $noLeadingZero Hindari angka 0 di depan
     * @return string 6-digit numeric code
     */
    public static function generateFormatted(bool $noLeadingZero = true): string
    {
        if ($noLeadingZero) {
            $otp = (string) random_int(1, 9);
            for ($i = 1; $i < 6; $i++) {
                $otp .= random_int(0, 9);
            }
            return $otp;
        }

        return self::generate();
    }

    /**
     * Validasi format OTP (harus 6 digit angka)
     *
     * @param string $otp OTP yang divalidasi
     * @return bool True jika format valid
     */
    public static function isValidFormat(string $otp): bool
    {
        return preg_match('/^\d{6}$/', $otp) === 1;
    }

    /**
     * Mask OTP untuk ditampilkan (misal: 12***6)
     *
     * @param string $otp OTP yang akan di-mask
     * @param int $visibleAtStart Jumlah digit yang terlihat di awal
     * @param int $visibleAtEnd Jumlah digit yang terlihat di akhir
     * @return string Masked OTP
     */
    public static function mask(string $otp, int $visibleAtStart = 1, int $visibleAtEnd = 1): string
    {
        if (strlen($otp) !== 6) {
            return '******';
        }

        $masked = str_repeat('*', 6 - $visibleAtStart - $visibleAtEnd);

        return substr($otp, 0, $visibleAtStart) . $masked . substr($otp, -1, $visibleAtEnd);
    }
}
