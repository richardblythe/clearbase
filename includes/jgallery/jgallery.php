<?php
require_once (CLEARBASE_DIR . "/controllers/base.php");
class Clearbase_Controllers_JGallery extends Clearbase_Controllers_Base {

    public function GetName() {
        return "JGallery";
    }

    public function GetEditorOptions() {
        return array(
            'supports_description' => false
        );
    }
    
    public function Register() {
        $base_url = ClearBase::$url . 'includes/controllers/jgallery/';
        wp_register_script('tiny-color', $base_url . 'js/tinycolor-0.9.16.min.js', array('jquery',), '0.9.16', true);
        wp_register_script('jgallery', $base_url . 'js/jgallery.min.js', array('jquery', 'tiny-color'), false, true);
        wp_register_style('jgallery-font-awesome',  $base_url . 'css/font-awesome.min.css');
        wp_register_style('jgallery', $base_url . 'css/jgallery.min.css');        
    }
    
    public function Enqueue() {
        wp_enqueue_script('jgallery');
        wp_enqueue_style('jgallery-font-awesome');
        wp_enqueue_style('jgallery');
    }
    
    
    public function Render() {
        ?>
        <div id="<?php echo $this->element_id; ?>">
            
      <?php  
        $albums = array();
        for ($a = 0; $a < $this->album_count; $a++) {
            if ($this->gallery_info->albums[$a]->image_count == 0)
                continue;
            $album = $this->GetAlbum($this->gallery_info->albums[$a]->ID, OBJECT);
            ?>
            <div class="album" data-jgallery-album-title="<?php echo $album->info->title; ?>">
                <h1><?php echo $album->info->title; ?></h1>
                <?php
                $image_count = count($album->images);
                $directory_name = ClearBase::$data->get_default_thumbs_directory_name();
                for ($i = 0; $i < $image_count; $i++) { 
                    ?><a href="<?php echo $this->GetUrl($album->ID, $album->images[$i]->ID); ?>">
                        <img src="<?php echo $this->GetUrl($album->ID, $album->images[$i]->ID, $directory_name); ?>" alt="<?php echo $album->images[$i]->title ?>" />
                    </a>
                    <?php
                }
                ?>
            </div>
            <?php  
        }
        ?>
        </div>
            <script type="text/javascript">
              jQuery(document).ready(function() {
                jQuery("#<?php echo $this->element_id; ?>").jGallery( {
                    mode : '<?php echo $this->atts['mode'] ? $this->atts['mode'] : 'standard'; ?>',
                    width: "<?php echo $this->atts['width'] ? $this->atts['width'] : '100%'; ?>",
                    height: "<?php echo $this->atts['height'] ? $this->atts['height'] : '600px'; ?>"
                } );
              });
            </script>
    <?php
      
    }
   
    
    public function WPFooter() {

    }
    
}

new ClearBase_Controllers_JGallery();