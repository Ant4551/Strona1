<!-- header styles -->

<?php
   $localFonts = apply_filters('get_local_fonts', '');
?>
<?php if ($localFonts) : ?> 
   <link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/css/<?php echo $localFonts; ?>" media="screen" type="text/css" />
<?php else : ?>
   <?php endif; ?>
<link id="u-google-font" rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:100,100i,200,200i,300,300i,400,400i,500,500i,600,600i,700,700i,800,800i,900,900i|Open+Sans:300,300i,400,400i,500,500i,600,600i,700,700i,800,800i">
<style> .u-header {
  background-image: none;
}
.u-header .u-sheet-1 {
  min-height: 114px;
}
.u-header .u-image-1 {
  width: 93px;
  height: 93px;
  margin: 22px auto 0 0;
}
.u-header .u-logo-image-1 {
  width: 100%;
  height: 100%;
}
.u-header .u-menu-1 {
  margin: -61px auto 22px 220px;
}
.u-header .u-nav-1 {
  font-size: 1rem;
}
.u-header .u-nav-link-2 {
  padding: 10px 20px;
}
.u-header .u-nav-2 {
  font-size: 1.25rem;
}
@media (max-width: 1199px) {
  .u-header .u-image-1 {
    width: 93px;
    height: 93px;
  }
  .u-header .u-menu-1 {
    width: auto;
    margin-top: -61px;
    margin-left: 220px;
  }
  .u-header .u-nav-1 {
    letter-spacing: normal;
  }
}
@media (max-width: 767px) {
  .u-header .u-menu-1 {
    margin-left: 165px;
  }
}
@media (max-width: 575px) {
  .u-header .u-menu-1 {
    margin-left: 104px;
  }
}</style>
