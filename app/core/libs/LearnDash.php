<?php

/*
    @author Pablo Bozzolo < boctulus@gmail.com >
*/

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Posts;

// if (!class_exists('WpProQuiz_Model_AnswerTypes')){
//     require_once WP_ROOT_PATH . 'wp-content/plugins/sfwd-lms/includes/lib/wp-pro-quiz/lib/model/WpProQuiz_Model_AnswerTypes.php';
// }

class LearnDash
{
    // Evitar crear o actualizar pregunta si ya existe
    static protected $skip_if_exists = true; 

    /*
        Crea (o actualiza) pregunta 
        Crea respuestas y las asocia a la pregunta
        Se asocia la pregunta a un quiz de un curso

        Las respuestas deben tener esta estructura:

        $answers = [
            [
                'text' => 'Hacer una cosa a las '. at(),
                'is_correct' => (bool) rand(0, 1),
                'points' => 1
            ],
            [
                'text' => 'Hacer otra cosa a las '. at(),
                'is_correct' => (bool) rand(0, 1),
                'points' => 1
            ],
            // ...
        ];
    */
    static function createQuestion(int $quiz_id, string $question, $question_title = null, string $tip = '', $ext_img_url = null, $answers, $total_points = null)
    {
        $question = Strings::convertEncoding(trim($question));

        if (empty($question)){
            // dd("Skiping existing question");
            return;
        }

        $question_title = Strings::convertEncoding(trim($question_title));
        $tip            = Strings::convertEncoding(trim($tip));

        $author_id      = Users::getAdminID();

        $quiz_name      = Posts::getPost(48)['post_name']; // Ej: "examen-clase-b" o sea el slug del quiz

        /*	
            Creo Pregunta
        */

        $post_data = array(
            'post_content'  => $question,
            'post_title'    => $question_title, // -> slug
            'post_author'   => $author_id, // ID del autor del post
            'post_status'   => 'publish', // Estado del post: 'publish', 'draft', etc.
            'post_type'     => 'sfwd-question', // Tipo de post: 'post', 'page', etc.
        );

        // // Buscar posts existentes con el mismo título y contenido

        global $wpdb;

        $wp = $wpdb->prefix;

        DB::getConnection();
        
        $row = DB::selectOne("SELECT * FROM {$wp}posts WHERE post_type='sfwd-question' AND post_title='$question_title' AND post_content='$question'");
    
        $mode = 'CREATE';

        if (!empty($row)) {
            if (static::$skip_if_exists){
                dd("Skiping existing question");
                return false;
            }
            
            dd("Updating question $question_title");
            $mode = 'UPDATE';

            // Actualizar el post existente
            $existing_post_id = $row['ID'];
            $post_data['ID']  = $existing_post_id;

            wp_update_post($post_data);
            $question_id = $existing_post_id;
        } else {
            dd("Creating question $question_title");

            // Insertar un nuevo post
            $question_id = wp_insert_post($post_data);
        }

        // Asocio imagen destacada si se especifica

        if (!empty($ext_img_url)){
            // Subir la imagen y obtener la ID de la imagen adjunta
            $image_id = Posts::uploadImage($ext_img_url);

            if (is_wp_error($image_id)) {
                throw new \Exception("Error al subir imagen desde '$ext_img_url");
            }

            // Asociar la imagen adjunta al post
            Posts::setDefaultImage($question_id, $image_id);	
        }

        /*
            Asocio el nombre del quiz a cada pregunta
        */

        Posts::setMeta($question_id, 'Quiz name', $quiz_name);
    
        /*
            De momento, solo actualizo la pregunta y no las respuestas
            Asi que evito continuar
        */

        if ($mode == 'UPDATE'){
            return $question_id;
        }

        /*
            Creo respuestas

            Usa WpProQuiz_Model_Question
        */

        $answers_ay = [];

        foreach ($answers as $answer){
            $a = new \WpProQuiz_Model_AnswerTypes();

            dd("Creando respuesta: {$answer['text']}", "$question_title ($quiz_name)");

            $a
            ->setAnswer(Strings::convertEncoding($answer['text']))
            ->setPoints($answer['points'])
            ->setCorrect($answer['is_correct']);	

            $answers_ay[] = $a;
        }

        // Serializa el array de respuestas
        $serialized_answer_data = serialize($answers_ay);
       
        $wpdb->insert(
            "{$wp}learndash_pro_quiz_question", 
            [
                'quiz_id' => $quiz_id,
                'online' => 1,
                'previous_id' => 0,
                'sort' => 1,
                'title' => $question_title,
                'points' => $total_points,
                'question' => $question,
                'correct_msg' => '',
                'incorrect_msg' => '',
                'correct_same_text' => 0,
                'tip_enabled' => (empty($tip) ? 0 : 1),
                'tip_msg' => empty($tip) ? '' : $tip,
                'answer_type' => 'single', //
                'show_points_in_box' => 0,
                'answer_points_activated' => 0,
                'answer_data' => $serialized_answer_data,
                'category_id' => 0,
                'answer_points_diff_modus_activated' => 0,
                'disable_correct' => 0,
                'matrix_sort_answer_criteria_width' => 20,
                // ...
                'answer_data' => $serialized_answer_data,
            ]
        );

        $quiz_id = $wpdb->insert_id;

        // dd($quiz_id, 'QUIZ ID');


        /*
            Creo "puente" entre pregunta y pregunta-respuesta

            post_id		apunta al ID del post en "wp_posts" de tipo "sfwd-question"
            meta_key	question_pro_id
            meta_value	{id de wp_learndash_pro_quiz_question}
        */

        $wpdb->insert(
            "{$wp}postmeta", 
            [
                'post_id'     => $question_id,
                'meta_key'    => 'question_pro_id',
                'meta_value'  => $quiz_id,
            ]
        );

        $pid_puente = $wpdb->insert_id;

        // dd($pid_puente, 'ID EN TABLA PUENTE');

        return $question_id;
    }

    static function getQuestions($limit = null, $offset = null, $order_by = null)
    {
        $ids = Posts::getIDs('sfwd-question', null, $limit, $offset, null, $order_by);

        $rows = [];
        foreach ($ids as $id){           
            $rows[] = static::getQuestion($id);
        }

        return $rows;
    }

    static function getQuestion($question_id){
        if (!is_numeric($question_id)){
            throw new \InvalidArgumentException("Se espera que question_id sea un numero entero");
        }

        $question = Posts::getPost($question_id);

        if ($question === null){
            return null;
        }

        $q_content = $question['post_content'];
        $q_title   = $question['post_title'];
        $q_image   = Posts::getImages($question_id, true);

        // dd($q_content, 'QUESTION');

        global $wpdb;

        $wp = $wpdb->prefix;

        DB::getConnection();
        $bridge = DB::selectOne("SELECT meta_value FROM {$wp}postmeta WHERE meta_key = 'question_pro_id' AND post_id = $question_id");


        // answers
        $quiz_question_id = $bridge['meta_value'];

        $quiz        = DB::selectOne("SELECT * FROM {$wp}learndash_pro_quiz_question WHERE id = $quiz_question_id");
        $answer_data = unserialize($quiz['answer_data']);
        $tip         = $quiz['tip_msg'];
        $points      = $quiz['points'];
        // $q_content   = $quiz['question'];
        // $q_title     = $quiz['title']

        /*
            Recupero atributos de instancia de WpProQuiz_Model_AnswerTypes
        */

        $answers     = [];
        $are_correct = 0;
        foreach ($answer_data as $answer){
            $a_text       = $answer->getAnswer();
            $a_is_correct = $answer->isCorrect();
            $a_points     = $answer->getPoints(); 
            
            $answers[] = [
                'text'       => $a_text,
                'points'     => $a_points,
                'is_correct' => $a_is_correct ? 'true' : 'false'
            ];

            if ($a_is_correct){
                $are_correct++;
            }
        }

        // dd($tip, 'TIP');
        // dd($points, 'POINTS');

        $explanation = Metabox::get($question_id, 'explanation');

        return [
            'question'      => $question,
            'quiz-name'     => Posts::getMeta($question_id, 'Quiz name'),
            'image'         => $q_image,
            'tip'           => $tip,
            'explanation'   => $explanation, 
            'points'        => $points,
            'answers'       => $answers,
            'total_correct' => $are_correct
        ];
    }

    /*
        Quizes dado su "name"

        Ej:

        'examen-clase-b'
    */
    static function getQuestionsByQuizName(string $quiz_name, $limit, $order_by = 'RAND()', $fields = '*'){
        return Posts::getByMeta('_quiz-name', $quiz_name, 'sfwd-question', null, $limit, null, $order_by, $fields);
    }

    static function getQuestionIDsByQuizName($quiz_name){
        global $wpdb;

        $wp = $wpdb->prefix;

        $query = "SELECT ID  FROM `{$wp}posts` WHERE `post_type` = 'sfwd-quiz' AND post_name = '$quiz_name'";
        
        return $wpdb->get_var($query);
    }

    static function deleteQuestion(int $question_id) {
        global $wpdb;

        $wp = $wpdb->prefix;

        // Obtiene el ID de la pregunta en la tabla puente
        $query = $wpdb->prepare("SELECT meta_value FROM {$wp}postmeta WHERE post_id = %d AND meta_key = 'question_pro_id'", $question_id);
        $quiz_id = $wpdb->get_var($query);

        if ($quiz_id) {
            // Elimina las respuestas asociadas a la pregunta
            $wpdb->delete("{$wp}learndash_pro_quiz_question", ['question_id' => $quiz_id]);

            // Elimina el registro de la tabla puente
            $wpdb->delete("{$wp}postmeta", ['post_id' => $question_id, 'meta_key' => 'question_pro_id']);

            // Elimina la pregunta en sí
            wp_delete_post($question_id, true); // true para borrarlo permanentemente
        }
    }

    // Funcionamiento no-verificado
    static function purgeQuiz(int $quiz_id) {
        global $wpdb;

        $wp = $wpdb->prefix;

        // Obtiene todas las preguntas asociadas al quiz
        $query = $wpdb->prepare("SELECT post_id FROM {$wp}postmeta WHERE meta_key = 'question_pro_id' AND meta_value = %d", $quiz_id);
        $question_ids = $wpdb->get_col($query);

        foreach ($question_ids as $question_id) {
            self::deleteQuestion($question_id);
        }
    }

    // OK
    static function purgeAllQuizes() {
        global $wpdb;
        $wp = $wpdb->prefix;

        DB::getConnection();

        DB::delete("DELETE FROM {$wp}learndash_pro_quiz_question");
        DB::delete("DELETE FROM {$wp}posts WHERE post_type='sfwd-question'");
        DB::delete("DELETE FROM {$wp}postmeta WHERE meta_key = 'question_pro_id'");
        DB::delete("DELETE FROM {$wp}postmeta WHERE meta_key = '_Quiz name'");       
    }


}