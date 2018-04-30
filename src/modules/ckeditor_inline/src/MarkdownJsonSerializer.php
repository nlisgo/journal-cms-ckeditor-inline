<?php

namespace Drupal\ckeditor_inline;

use League\CommonMark\Block\Element;
use League\CommonMark\ElementRendererInterface;
use League\CommonMark\Node\Node;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MarkdownJsonSerializer implements NormalizerInterface
{

    private $htmlRenderer;
    private $depthOffset = null;

    public function __construct(ElementRendererInterface $htmlRenderer)
    {
        $this->htmlRenderer = $htmlRenderer;
    }

    /**
     * @param Element\Document $object
     */
    public function normalize($object, $format = null, array $context = []) : array
    {
        return $this->convertChildren($object);
    }

    private function convertChildren(Element\Document $document) : array
    {
        $nodes = [];
        $this->resetDepthOffset();
        foreach ($document->children() as $node) {
            if ($child = $this->convertChild($node)) {
                $nodes[] = $child;
            }
        }

        return $this->implementHierarchy($nodes);
    }

    private function resetDepthOffset()
    {
        $this->depthOffset = $this->setDepthOffset(null, true);
    }

    private function setDepthOffset($depthOffset, bool $override = false)
    {
        if (is_null($this->depthOffset) || $override === true) {
            $this->depthOffset = $depthOffset;
        }
    }

    private function getDepthOffset()
    {
        return $this->depthOffset;
    }

    /**
     * @return array|null
     */
    private function convertChild(Node $node)
    {
        switch (true) {
            case $node instanceof Element\Heading:
                if ($rendered = $this->htmlRenderer->renderBlock($node)) {
                    $depthOffset = $this->getDepthOffset();
                    $heading = (int) preg_replace('/^h([1-5])$/', '$1', $rendered->getTagName());
                    if (is_null($depthOffset) || $heading === 1) {
                        $depthOffset = 1 - $heading;
                        $this->setDepthOffset($depthOffset, ($heading === 1));
                    }

                    // Only allow 2 levels of hierarchy.
                    $depth = (($heading + $depthOffset) === 1) ? 1 : 2;

                    return [
                        'type' => 'section',
                        'title' => $rendered->getContents(),
                        'depth' => $depth,
                    ];
                }
                break;
            case $node instanceof Element\HtmlBlock:
                if ($rendered = $this->htmlRenderer->renderBlock($node)) {
                    $contents = trim($rendered);
                    if (preg_match('/^<table.*<\/table>/', $contents)) {
                        return [
                            'type' => 'table',
                            'tables' => [$contents],
                        ];
                    }
                }
                break;
            case $node instanceof Element\Paragraph:
                if ($rendered = $this->htmlRenderer->renderBlock($node)) {
                    return [
                        'type' => 'paragraph',
                        'text' => $rendered->getContents(),
                    ];
                }
                break;
            case $node instanceof Element\ListBlock:
                return $this->processListBlock($node);
                break;
            case $node instanceof Element\BlockQuote:
                if ($rendered = $this->htmlRenderer->renderBlock($node)) {
                    return [
                        'type' => 'quote',
                        'text' => [
                            [
                                'type' => 'paragraph',
                                'text' => trim(preg_replace('/^[\s]*<p>(.*)<\/p>[\s]*$/s', '$1', $rendered->getContents())),
                            ],
                        ],
                    ];
                }
                break;
            case $node instanceof Element\FencedCode:
            case $node instanceof Element\IndentedCode:
                if ($rendered = $this->htmlRenderer->renderBlock($node)) {
                    return [
                        'type' => 'code',
                        'code' => trim(preg_replace('/^[\s]*<code>(.*)<\/code>[\s]*$/s', '$1', $rendered->getContents())),
                    ];
                }
                break;
        }

        return null;
    }

    private function implementHierarchy(array $nodes) : array
    {
        // Organise 2 levels of section.
        for ($level = 2; $level > 0; $level--) {
            $hierarchy = [];
            for ($i = 0; $i < count($nodes); $i++) {
                $node = $nodes[$i];

                if ($node['type'] === 'section' && isset($node['depth']) && $node['depth'] === $level) {
                    unset($node['depth']);
                    for ($j = $i + 1; $j < count($nodes); $j++) {
                        $sectionNode = $nodes[$j];
                        if ($sectionNode['type'] === 'section' && isset($sectionNode['depth']) && $sectionNode['depth'] <= $level) {
                            break;
                        } else {
                            $node['content'][] = $sectionNode;
                        }
                    }
                    $i = $j - 1;
                    if (empty($node['content'])) {
                        continue;
                    }
                }
                $hierarchy[] = $node;
            }
            $nodes = $hierarchy;
        };

        return $hierarchy ?? [];
    }

    private function processListBlock(Element\ListBlock $block)
    {
        $gather = function (Element\ListBlock $list) use (&$gather, &$render) {
            $items = [];
            foreach ($list->children() as $item) {
                foreach ($item->children() as $child) {
                    if ($child instanceof Element\ListBlock) {
                        $items[] = [$render($child)];
                    } elseif ($item = $this->htmlRenderer->renderBlock($child)) {
                        $items[] = $item->getContents();
                    }
                }
            }

            return $items;
        };

        $render = function (Element\ListBlock $list) use ($gather) {
            return [
                'type' => 'list',
                'prefix' => (Element\ListBlock::TYPE_ORDERED === $list->getListData()->type) ? 'number' : 'bullet',
                'items' => $gather($list),
            ];
        };

        return $render($block);
    }

    public function supportsNormalization($data, $format = null) : bool
    {
        return $data instanceof Element\Document;
    }

}
