(function(blocks, element, editor) {
    var el = element.createElement;
    var TextControl = wp.components.TextControl;
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
                    // const currentContent = select('core/editor').getEditedPostContent()
                    // const newContent = currentContent + response.content;

                    // console.log({newContent, currentContent, response})

                    // setAttributes({ content: response.content });
                    // //dispatch('core/editor').editPost({ content: newContent })
                    // setLoading(false);
                    const genereatedContent = response.content;

                    const newBlock = wp.blocks.createBlock('core/paragraph', {
                        content: genereatedContent
                    })

                    replaceBlock(props.clientId, newBlock);
                })
            }

            return el('div', {},
                el(TextControl, {
                    label: 'Indtast en prompt',
                    value: attributes.prompt,
                    onChange: (value) => setAttributes({ prompt: value })
                }),
                el('button', {
                    onClick: generateContent,
                    disabled: loading
                }, loading ? 'Genererer...' : 'Generér indhold'),
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