<?php
/*
Plugin Name: Gutenberg LLM Content Generator
Description: Tilføjer dynamisk indholdsgenerering ved hjælp af en LLM til Gutenberg Editor.
Version: 1.0
Author: Leo Knudsen
*/
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists('gutenberg_llm_block_register')) {
    /**
     * Register LLM Block Generator block
     * Block takes a prompt message from the user and sends it to the LLM API to generate content.
     *
     * @return void
     */
    function gutenberg_llm_block_register(): void {
        wp_register_script(
            'gutenberg-llm-block',
            plugins_url('block.js', __FILE__),
            array('wp-blocks', 'wp-element', 'wp-editor')
        );

        register_block_type('gutenberg-llm/llm-block', array(
            'editor_script' => 'gutenberg-llm-block',
        ));
    }
}

add_action('init', 'gutenberg_llm_block_register');

if ( ! function_exists('gutenberg_llm_register_rest_route') ) {
    /**
     * Registering REST Route for LLM content generation
     *
     * @return void
     */
    function gutenberg_llm_register_rest_route(): void {
        register_rest_route( 'll', '/get-prompts', array(
            'methods' => 'GET',
            'callback' => 'gutenberg_llm_get_prompts',
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route( 'llm/v1', '/generate/', array(
            'methods' => 'POST',
            'callback' => 'gutenberg_llm_generate_content',
            'permission_callback' => '__return_true'
        ));

        register_rest_route('llm/v1', '/save-prompt/', array(
            'methods' => 'POST',
            'callback' => 'gutenberg_llm_save_prompt',
            'permission_callback' => '__return_true'
        ));
    }
}

add_action( 'rest_api_init', 'gutenberg_llm_register_rest_route' );

if ( ! function_exists('gutenberg_llm_get_prompts') ) {
    function gutenberg_llm_get_prompts(WP_REST_Request $request) {
        global $wpdb;

        $prompts = $wpdb->get_results(
            "SELECT prompt FROM " . $wpdb->prefix . "prompts"
        );

        return ["prompts" => $prompts];
    }
}

if ( ! function_exists('gutenberg_llm_generate_content') ) {
    /**
     * Generate content using LLM API based on user prompt input
     * Send back response back to the user with generated content from LLM
     *
     * @param WP_REST_Request $request
     * @return void
     */
    function gutenberg_llm_generate_content(WP_REST_Request $request): array {
        $prompt = $request->get_param('prompt');

        $api_key = get_option('OPEN_AI_API_KEY');
        $url = 'https://api.openai.com/v1/chat/completions';
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    "role" => "system",
                    "content" => "You are a code generator assistant. you are helping with generating gutenberg blocks for WordPress."
                ],
                [
                    "role" => "user",
                    "content" => "Only generate WordPress Gutenberg HTML blocks without explanations, " . $prompt
                ] 
            ],
            'max_tokens' => 1000,
            'temperature' => 0.2,
        ];

        $response = wp_remote_post($url, [
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        ]);

        if ( is_wp_error($response) ) {
            return new WP_Error( 'llm_request_failed', 'Kunne ikke hente indhold fra LLM', array( 'status' => 500 ) );
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body ,true);
        $generated_code = extract_code_from_response($result["choices"][0]["message"]["content"]);

        return array(
            'content' => $generated_code
        );
    }
}

if ( ! function_exists('extract_code_from_response') ) {
    /**
     * Extracts code from the LLM response
     *
     * @param string $text
     * @return string
     */
    function extract_code_from_response($text): string {
        // Match kodeblokke, der er markeret med ```
        preg_match_all('/```(.*?)```/s', $text, $matches);

        if (!empty($matches[1])) {
            // Returner kun den kode, der findes inden for ``` ```
            return implode("\n", $matches[1]);
        }
        
        // Hvis der ikke er kodeblokke, returner hele teksten.
        return $text;
    }
}

if ( ! function_exists('setup_prompt_database_table') ) {
    function setup_prompt_database_table() {
        global $wpdb;

        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "prompts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                prompt TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;"
        );
    }
}

add_action('init', 'setup_prompt_database_table');