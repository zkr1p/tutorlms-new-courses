<?php

namespace boctulus\TutorNewCourses\custom_post_types;

use boctulus\TutorNewCourses\core\libs\CPT;

class ReviewCPT extends CPT
{
    protected $type   = "review";
    protected $single = "Recensione";
    protected $plural = "Recensioni";
    protected $custom_fields = [
        'title'     => ['label' => 'Commento' ], 
        
        'score'     => ['label' => 'Punteggio', 'type' => 'select', 
                            'options' => [
                                '1' => '⭐', 
                                '2' => '⭐⭐',
                                '3' => '⭐⭐⭐',
                                '4' => '⭐⭐⭐⭐',
                                '5' => '⭐⭐⭐⭐⭐'
                            ]
        ],

        'author'    => ['label' => 'Autore',    'type' => 'text'],
 
        'gender'    => ['label' => 'Genero',    'type' => 'select', 
                            'options' => [
                                'female'    => 'Femmina', 
                                'male'      => 'Maschio'
                            ]
        ],
    ];

    protected $hidden = [
        'comments', 
        'stats'        
    ];

    protected $readonly = [
        // ..
    ];

    public function __construct($singular=null, $plural=null, $custom_fields=[])
    {
       parent::__construct($singular, $plural, $custom_fields);

       $this->addCustomfilters();
    }

    public function register_post_type()
    {
        $labels = [
            'name' => _x($this->plural, 'post type general name'),
            'singular_name' => _x($this->single, 'post type singular name'),
            'add_new' => _x('Add ' . $this->single, $this->single),
            'add_new_item' => __('Add') . ' '. $this->single,
            'edit_item' => __('Edit ' . $this->single),
            'new_item' => __('New ' . $this->single),
            'view_item' => __('View ' . $this->single),
            'search_items' => __('Search ' . $this->plural),
            'not_found' => __('No ' . $this->plural . ' Found'),
            'not_found_in_trash' => __('No ' . $this->plural . ' found in Trash'),
            'parent_item_colon' => ''
        ];

        $args = [
            'labels' => $labels,
            'description' => __('Risultati per le recensioni', 'text_domain'),
            'supports' => ['title', 'editor',  'custom-fields'],
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 5,
            // https://developer.wordpress.org/resource/dashicons/#minus
            'menu_icon' => 'dashicons-testimonial', 
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'post',
            'rewrite' => ['slug' => strtolower($this->plural)],
        ];

        register_post_type($this->type, $args);
    }

    public function add_custom_metaboxes()
    {
        add_meta_box('result_metabox', 'Risultato', [$this, 'metaboxCallback'], $this->type, 'normal', 'high');
    } 
    
    /*
        Custom filters
    */

    public function addCustomfilters() {
        if (empty($this->type)) {
            return;
        }

        // filtros
    }

    
}


