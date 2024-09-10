<?php
/*
Plugin Name: Gutenberg LLM Content Generator
Description: Tilføjer dynamisk indholdsgenerering ved hjælp af en LLM til Gutenberg Editor.
Version: 1.0
Author: Dit Navn
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists('gutenberg_llm_block_register')) {
    function gutenberg_llm_block_register() {
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

function gutenberg_llm_register_rest_route() {
    register_rest_route( 'llm/v1', '/generate/', array(
        'methods' => 'POST',
        'callback' => 'gutenberg_llm_generate_content',
        'permission_callback' => '__return_true'
    ));
}
add_action( 'rest_api_init', 'gutenberg_llm_register_rest_route' );

function gutenberg_llm_generate_content(WP_REST_Request $request) {
    $prompt = $request->get_param('prompt');

    $api_key = get_option('OPEN_API_KEY');
    $url = 'https://api.openai.com/v1/chat/completions';
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                "role" => "system",
                "content" => "Du er en kodegenerator. Du skal kun generere ren kode baseret på brugerens prompt. Medtag ikke forklaringer, kommentarer eller tekst uden for koden."
            ],
            [
                "role" => "user",
                "content" => "Generer kun WordPress Gutenberg HTML blokke uden forklaringer til at " . $prompt
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
        "blocktype" => "core/paragraph",
        'content' => $generated_code
    );
}

function extract_code_from_response($text) {
    // Match kodeblokke, der er markeret med ```
    preg_match_all('/```(.*?)```/s', $text, $matches);

    
    
    if (!empty($matches[1])) {
        // Returner kun den kode, der findes inden for ``` ```
        return implode("\n", $matches[1]);
    }
    
    // Hvis der ikke er kodeblokke, returner hele teksten.
    return $text;
}