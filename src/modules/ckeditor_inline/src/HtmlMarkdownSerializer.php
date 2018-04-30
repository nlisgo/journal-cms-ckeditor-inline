<?php

namespace Drupal\ckeditor_inline;

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class HtmlMarkdownSerializer implements NormalizerInterface
{

    private $htmlConverter;

    private $config = [
        'header_style' => 'atx',
    ];

    public function __construct(HtmlConverter $htmlConverter)
    {
        $this->htmlConverter = $htmlConverter;
        foreach ($this->config as $k => $v) {
            $this->htmlConverter->getConfig()->setOption($k, $v);
        }
    }

    /**
     * @param string $object
     */
    public function normalize($object, $format = null, array $context = []) : string
    {
        $markdown = $this->htmlConverter->convert($object);
        $markdown = $this->gatherTables($markdown);
        return preg_replace('/(<\/table>)([^\s\n])/', '$1'.PHP_EOL.PHP_EOL.'$2', $markdown);
    }

    public function gatherTables($markdown) {
        return preg_replace_callback(
            '~(<table>.+</table>)~s',
            function ($match) {
                return preg_replace(['/\s*'.PHP_EOL.'+\s*/', '~\s*(</?(table|thead|tbody|th|tr|td)>)\s+(</?(table|thead|tbody|th|tr|td)>)\s*~'], ['', '$1$3'], $match[0]);
            },
            $markdown
        );
    }

    public function supportsNormalization($data, $format = null) : bool
    {
        return is_string($data);
    }

}
