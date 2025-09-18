<?php

use boctulus\TutorNewCourses\core\libs\Config;

if (!function_exists('credits_to_author')){
    function credits_to_author()
    {
        global $credits_added;

        if ($credits_added){
            return;
        }

        if (isset($_GET['credits'])){
            add_action('wp_footer', function(){ 
                $app_name = Config::get()['app_name'] ?? 'Plugin development';
                ?>
                    <div id="dev-credits" style="
                    height: 60px;
                    text-align: center; margin: auto;
                    width: 100%;
                    padding-top: 20px;
                    padding-bottom: 20px;
                    background-color: #f0ad4e; ">
                    <strong><?= $app_name ?></strong> by <b>Pablo Bozzolo</b> < boctulus@gmail.com >
                    </div>
        
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            if (window.location.href.includes('credits')) {
                                var creditsElement = document.getElementById('dev-credits');
                                if (creditsElement) {
                                    creditsElement.scrollIntoView({ behavior: 'smooth' });
                                }
                            }
                        });
                    </script>
                <?php    
            }); 

            // Solo tiene sentido en el mu-plugin
            $credits_added = true;
        } 
        
    }
}
