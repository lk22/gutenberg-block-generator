(function(blocks, element, editor) {
    var el = element.createElement;
    var TextControl = wp.components.TextControl;
    var WYSIWYG = wp.editor.RichText;
    var useState = wp.element.useState;
    var dispatch = wp.data.dispatch;
    var useDispatch = wp.data.useDispatch;
    var select = wp.data.select;
    var RawHTML = wp.element.RawHTML;
    var BlockEditor = wp.blockEditor;

    blocks.registerBlockType('gutenberg-llm/llm-block', {
        title: 'LLM Content Generator',
        icon: 'smiley',
        category: 'common',
        attributes: {
            prompt: {
                type: 'string',
                default: ''
            },
            content: {
                type: 'string',
                default: ''
            }
        },
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const [loading, setLoading] = useState(false);
            const { replaceBlock } = useDispatch('core/block-editor');
            
            const generateContent = () => {
                setLoading(true);
                wp.apiFetch({
                    url: window.location.origin + '/wp-json/llm/v1/generate', // Brug fuld URL i stedet for 'path'
                    method: 'POST',
                    data: { prompt: attributes.prompt }
                }).then(response => {
                    console.log(response)
                    const genereatedContent = response.content;

                    const blocks = wp.blocks.parse(genereatedContent);

                    // create a new block with the generated content
                    const clientId = blocks[0].clientId;

                    // insert the new block
                    dispatch('core/block-editor').insertBlock(blocks[0], props.clientId);

                    // if the first block has inner blocks we need to add them to the new block
                    if (blocks[0].innerBlocks) {
                        blocks[0].innerBlocks.forEach(innerBlock => {
                            dispatch('core/block-editor').insertBlock(innerBlock, clientId);
                        });
                    }

                    setLoading(false);

                }).catch(error => {
                    console.error(error);
                    setLoading(false);
                });
            }

            const savePrompt = () => {
                setLoading(true)
                wp.apiFetch({
                    url: window.location.origin + "/wp-json/llm/save-prompt",
                    method: "POST",
                    data: {
                        prompt: attributes.prompt
                    }
                }).then(response => {
                    console.log(response)
                })
            }

            return el('div', {},
                el(TextControl, {
                    label: 'Indtast en prompt',
                    value: attributes.prompt,
                    onChange: (value) => setAttributes({ prompt: value })
                }),
                el('button', {
                    onClick: savePrompt,
                    disabled: loading
                }, loading ? 'saving...' : 'Save Prompt'),
                el('button', {
                    onClick: generateContent,
                    disabled: loading
                }, loading ? 'Generating...' : 'Generate content'),
                el('div', { className: 'generated-content' },
                    // Viser det genererede indhold i editoren
                    el(RawHTML, {}, attributes.content)
                )
            );
        },
        save: function(props) {
            return el(RawHTML, {}, props.attributes.content);
        }
    })
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.editor
)