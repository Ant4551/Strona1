<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after
 */
?>
		</div><!-- #content -->
<?php $language = ThemeMultiLanguagesResolver::get_current_language();
global $hideFooter; if (!$hideFooter) {
    $translations = '';
    if ($language) {
        if (file_exists(get_template_directory() . '/' . 'template-parts/' . '/translations/' . $language .'/footer-content' . '.php')) {
            $translations = '/translations/' . $language;
        }
    }
    ob_start();
    get_template_part('template-parts' . $translations . '/footer-content');
    $footer_dialog_path = get_template_directory() . '/template-parts/' . $translations . '/footer-dialogs.php';
    if (file_exists($footer_dialog_path)) {
        include $footer_dialog_path;
    }
} ?>
        <?php $showBackLink = get_option('np_hide_backlink') ? false : true; ?>
<?php if ($showBackLink) : $GLOBALS['theme_backlink'] = true; ?>
<section class="u-backlink u-clearfix u-grey-80">    </section>
<?php endif; ?>
        
	</div><!-- .site-inner -->
</div><!-- #page -->

<?php wp_footer(); ?>
<?php back_to_top(); ?>
</body>
</html>
<?php $footer = ob_get_clean();
if (function_exists('renderTemplate')) {
    renderTemplate($footer, '', 'echo', 'footer');
} else {
    echo $footer;
} ?>