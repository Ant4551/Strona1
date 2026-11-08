<?php $skip_min_height = false; ?><section class="u-align-center u-clearfix u-section-1" id="block-2">
  <div class="u-clearfix u-sheet u-sheet-1"><!--login_form-->
    <div class="u-form u-login-control u-form-1"><!--login_form_inner-->
      <?php global $pageLogin_custom_template;
	    $pathToTemplates = get_template_directory() . '/template-parts/' . $pageLogin_custom_template;
	    $pathToFormsTemplates = $pathToTemplates . '/forms/';
	    if (file_exists($pathToFormsTemplates . 'form.php')) {
		    include_once $pathToFormsTemplates . 'form.php';
	    } ?><!--/login_form_inner-->
    </div><!--/login_form--><!--login_form_forgot_password-->
    <!--/login_form_forgot_password--><!--login_form_create_account-->
    <!--/login_form_create_account-->
  </div>
</section><?php if ($skip_min_height) { echo "<style> .u-section-1, .u-section-1 .u-sheet {min-height: auto;}</style>"; } ?>
