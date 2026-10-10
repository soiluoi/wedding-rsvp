<?php
if (!defined('ABSPATH')) {
    exit;
}
$message = $view['error'] === 'inactive'
    ? 'Link này không còn hiệu lực.'
    : 'Link không đúng, bạn kiểm tra lại giúp mình nhé.';
?>
<p class="hello"><?php echo esc_html($message); ?></p>
<?php if ($settings['contact_info'] !== '') : ?>
    <p class="contact"><?php echo nl2br(esc_html($settings['contact_info'])); ?></p>
<?php endif; ?>
