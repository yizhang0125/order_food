<?php
/*
 * PHP QR Code encoder
 *
 * Simplified wrapper for QR code generation
 */

class QRcode {
    public static function png($text, $outfile = false, $level = 0, $size = 3, $margin = 4) {
        // Include Google Chart API for QR code generation
        $ch = curl_init();
        $url = 'https://chart.googleapis.com/chart?';
        $url .= 'chs=300x300&';
        $url .= 'cht=qr&';
        $url .= 'chl=' . urlencode($text) . '&';
        $url .= 'chld=L|' . $margin;
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $qrImage = curl_exec($ch);
        curl_close($ch);
        
        if ($outfile !== false) {
            file_put_contents($outfile, $qrImage);
            return true;
        } else {
            header('Content-Type: image/png');
            echo $qrImage;
            return true;
        }
    }
} 