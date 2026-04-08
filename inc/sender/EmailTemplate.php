<?php
/**
 * Converts Gutenberg HTML content into email-safe HTML.
 *
 * Wraps content in a table-based email template with inline styles.
 * Strips Gutenberg-specific classes/comments and converts to inline CSS.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Sender;

defined( 'ABSPATH' ) || exit;

class EmailTemplate {

    /**
     * Render a full email from post content.
     *
     * @param string $content      Gutenberg rendered HTML.
     * @param string $brand_name   Sender/brand name for header.
     * @param string $unsubscribe_url  One-click unsubscribe URL.
     * @param string $preview_text Preview text shown in inbox.
     *
     * @return string Full email HTML.
     */
    public static function render( $content, $brand_name = '', $unsubscribe_url = '#', $preview_text = '' ) {
        $body = self::convert_content( $content );

        if ( ! $brand_name ) {
            $settings   = get_option( 'snel_newsletter_settings', array() );
            $brand_name = $settings['from_name'] ?? get_bloginfo( 'name' );
        }

        $preview_html = '';
        if ( $preview_text ) {
            // Hidden preview text — shown in inbox but not in email body.
            $preview_html = '<div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">'
                . esc_html( $preview_text )
                . str_repeat( '&zwnj;&nbsp;', 40 ) // Pad to push other text out of preview
                . '</div>';
        }

        return '<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>' . esc_html( $brand_name ) . '</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { color: #3b82f6; }
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .email-content { padding: 20px 16px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: Arial, Helvetica, sans-serif;">
    ' . $preview_html . '

    <!-- Outer wrapper -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 24px 16px;">

                <!-- Email container -->
                <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background-color: #1a1a1a; padding: 28px 24px; text-align: center;">
                            <h1 style="color: #ffffff; font-size: 20px; font-weight: bold; margin: 0; font-family: Arial, Helvetica, sans-serif;">'
                                . esc_html( $brand_name ) .
                            '</h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="email-content" style="padding: 32px 24px; background-color: #ffffff;">
                            ' . $body . '
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 24px; text-align: center; border-top: 1px solid #e5e7eb; background-color: #fafafa;">
                            <p style="color: #9ca3af; font-size: 12px; line-height: 1.5; margin: 0 0 8px; font-family: Arial, Helvetica, sans-serif;">
                                You received this because you subscribed to our newsletter.
                            </p>
                            <a href="' . esc_url( $unsubscribe_url ) . '" style="color: #6b7280; font-size: 12px; text-decoration: underline; font-family: Arial, Helvetica, sans-serif;">
                                Unsubscribe
                            </a>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Convert Gutenberg HTML to email-safe inline CSS.
     */
    private static function convert_content( $html ) {
        // Remove Gutenberg comments.
        $html = preg_replace( '/<!--\s*\/?wp:.*?-->/s', '', $html );

        // Remove empty paragraphs.
        $html = preg_replace( '/<p[^>]*>\s*<\/p>/', '', $html );

        // Strip all class attributes (they don't work in email).
        $html = preg_replace( '/\s+class="[^"]*"/', '', $html );

        // Add inline styles to common elements.
        $replacements = array(
            // Paragraphs.
            '<p>'  => '<p style="color: #374151; font-size: 15px; line-height: 1.7; margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif;">',

            // Headings.
            '<h1>' => '<h1 style="color: #111827; font-size: 24px; font-weight: bold; line-height: 1.3; margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif;">',
            '<h2>' => '<h2 style="color: #111827; font-size: 20px; font-weight: bold; line-height: 1.3; margin: 0 0 14px; font-family: Arial, Helvetica, sans-serif;">',
            '<h3>' => '<h3 style="color: #111827; font-size: 17px; font-weight: bold; line-height: 1.4; margin: 0 0 12px; font-family: Arial, Helvetica, sans-serif;">',
            '<h4>' => '<h4 style="color: #111827; font-size: 15px; font-weight: bold; line-height: 1.4; margin: 0 0 10px; font-family: Arial, Helvetica, sans-serif;">',

            // Lists.
            '<ul>' => '<ul style="color: #374151; font-size: 15px; line-height: 1.7; margin: 0 0 16px; padding-left: 24px; font-family: Arial, Helvetica, sans-serif;">',
            '<ol>' => '<ol style="color: #374151; font-size: 15px; line-height: 1.7; margin: 0 0 16px; padding-left: 24px; font-family: Arial, Helvetica, sans-serif;">',
            '<li>' => '<li style="margin-bottom: 6px;">',

            // Blockquote.
            '<blockquote>' => '<blockquote style="border-left: 4px solid #d1d5db; padding: 12px 16px; margin: 0 0 16px; color: #6b7280; font-style: italic; font-family: Arial, Helvetica, sans-serif;">',

            // Horizontal rule.
            '<hr>'  => '<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">',
            '<hr/>' => '<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">',
            '<hr />' => '<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">',

            // Strong/em.
            '<strong>' => '<strong style="font-weight: bold; color: #111827;">',
        );

        $html = str_replace( array_keys( $replacements ), array_values( $replacements ), $html );

        // Handle images — make responsive.
        $html = preg_replace(
            '/<img([^>]*)>/i',
            '<img$1 style="max-width: 100%; height: auto; display: block; border-radius: 4px; margin: 0 0 16px;">',
            $html
        );

        // Handle links — add color.
        $html = preg_replace(
            '/<a\s+href="([^"]*)"([^>]*)>/i',
            '<a href="$1" style="color: #3b82f6; text-decoration: underline;"$2>',
            $html
        );

        // Handle Gutenberg buttons block.
        $html = preg_replace(
            '/<a\s+([^>]*style="[^"]*")([^>]*)>/i',
            '<a $1$2>',
            $html
        );

        // Wrap figures (image blocks) properly.
        $html = preg_replace( '/<figure[^>]*>/', '', $html );
        $html = str_replace( '</figure>', '', $html );
        $html = preg_replace( '/<figcaption[^>]*>/', '<p style="color: #9ca3af; font-size: 13px; text-align: center; margin: -8px 0 16px; font-family: Arial, Helvetica, sans-serif;">', $html );
        $html = str_replace( '</figcaption>', '</p>', $html );

        // Remove div wrappers from blocks.
        $html = preg_replace( '/<div[^>]*>/', '', $html );
        $html = str_replace( '</div>', '', $html );

        // Clean up extra whitespace.
        $html = preg_replace( '/\n\s*\n/', "\n", $html );

        return trim( $html );
    }

    /**
     * Generate plain text version from HTML.
     */
    public static function to_plain_text( $html ) {
        // Remove style/script tags.
        $text = preg_replace( '/<(style|script)[^>]*>.*?<\/\1>/si', '', $html );

        // Convert links to text + URL.
        $text = preg_replace( '/<a[^>]+href="([^"]*)"[^>]*>([^<]*)<\/a>/i', '$2 ($1)', $text );

        // Convert headings to uppercase.
        $text = preg_replace_callback( '/<h[1-4][^>]*>(.*?)<\/h[1-4]>/si', function ( $m ) {
            return strtoupper( strip_tags( $m[1] ) ) . "\n\n";
        }, $text );

        // Convert list items.
        $text = preg_replace( '/<li[^>]*>/i', '• ', $text );

        // Convert hr to dashes.
        $text = preg_replace( '/<hr[^>]*>/i', "\n---\n", $text );

        // Convert br to newline.
        $text = preg_replace( '/<br[^>]*>/i', "\n", $text );

        // Convert block elements to double newline.
        $text = preg_replace( '/<\/(p|div|blockquote|li|tr)>/i', "\n\n", $text );

        // Strip remaining tags.
        $text = strip_tags( $text );

        // Clean up whitespace.
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );
        $text = trim( $text );

        return $text;
    }
}
