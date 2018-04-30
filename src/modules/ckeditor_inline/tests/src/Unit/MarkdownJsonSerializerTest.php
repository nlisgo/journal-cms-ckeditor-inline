<?php

namespace Drupal\Tests\ckeditor_inline\Unit;

use Drupal\ckeditor_inline\MarkdownJsonSerializer;
use League\CommonMark\Block\Element\Document;
use League\CommonMark\DocParser;
use League\CommonMark\Environment;
use League\CommonMark\HtmlRenderer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MarkdownJsonSerializerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MarkdownJsonSerializer */
    private $normalizer;

    /** @var DocParser */
    private $docParser;

    /**
     * @before
     */
    protected function setUpNormalizer()
    {
        $environment = Environment::createCommonMarkEnvironment();
        $this->normalizer = new MarkdownJsonSerializer(new HtmlRenderer($environment));
        $this->docParser = new DocParser($environment);
    }

    /**
     * @test
     */
    public function it_is_a_normalizer()
    {
        $this->assertInstanceOf(NormalizerInterface::class, $this->normalizer);
    }

    /**
     * @test
     * @dataProvider canNormalizeProvider
     */
    public function it_can_normalize_documents($data, $format, bool $expected)
    {
        $this->assertSame($expected, $this->normalizer->supportsNormalization($data, $format));
    }

    public function canNormalizeProvider() : array
    {
        $document = new Document();

        return [
            'document' => [$document, null, true],
            'non-document' => [$this, null, false],
        ];
    }

    /**
     * @test
     * @dataProvider normalizeProvider
     */
    public function it_will_normalize_documents(array $expected, string $markdown)
    {
        $this->assertEquals($expected, $this->normalizer->normalize($this->createDocument($markdown)));
    }

    public function normalizeProvider() : array
    {
        return [
            'minimal' => [
                [],
                '',
            ],
            'single paragraph' => [
                [
                    [
                        'type' => 'paragraph',
                        'text' => 'Single paragraph',
                    ],
                ],
                'Single paragraph',
            ],
            'single table' => [
                [
                    [
                        'type' => 'table',
                        'tables' => [
                            '<table><tr><td>Cell one</td></tr></table>',
                        ],
                    ],
                ],
                '<table><tr><td>Cell one</td></tr></table>',
            ],
            'multiple tables' => [
                [
                    [
                        'type' => 'table',
                        'tables' => [
                            '<table><tr><td>Cell one</td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'table',
                        'tables' => [
                            '<table><tr><td>Cell two</td></tr></table>',
                        ],
                    ],
                ],
                $this->lines([
                    '<table><tr><td>Cell one</td></tr></table>',
                    '<table><tr><td>Cell two</td></tr></table>',
                ], 2),
            ],
            'simple list' => [
                [
                    [
                        'type' => 'paragraph',
                        'text' => 'Nested list:',
                    ],
                    [
                        'type' => 'list',
                        'prefix' => 'bullet',
                        'items' => [
                            'Item 1',
                            'Item 2',
                            [
                                [
                                    'type' => 'list',
                                    'prefix' => 'bullet',
                                    'items' => [
                                        'Item 2.1',
                                        [
                                            [
                                                'type' => 'list',
                                                'prefix' => 'number',
                                                'items' => [
                                                    'Item 2.1.1',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                $this->lines([
                    'Nested list:',
                    '- Item 1',
                    '- Item 2',
                    '  - Item 2.1',
                    '    1. Item 2.1.1',
                ]),
            ],
            'single blockquote' => [
                [
                    [
                        'type' => 'quote',
                        'text' => [
                            [
                                'type' => 'paragraph',
                                'text' => 'Blockquote line 1',
                            ],
                        ],
                    ],
                ],
                '> Blockquote line 1',
            ],
            'simple code sample' => [
                [
                    [
                        'type' => 'code',
                        'code' => $this->lines([
                            'Code sample line 1',
                            'Code sample line 2',
                        ], 2),
                    ],
                ],
                $this->lines([
                    '```',
                    'Code sample line 1',
                    'Code sample line 2',
                    '```',
                ], 2),
            ],
            'single section' => [
                [
                    [
                        'type' => 'section',
                        'title' => 'Section heading',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'text' => 'Single paragraph',
                            ],
                        ],
                    ],
                ],
                $this->lines([
                    '# Section heading',
                    'Single paragraph',
                ]),
            ],
            'preserve hierarchy' => [
                [
                    [
                        'type' => 'paragraph',
                        'text' => 'Paragraph 1.',
                    ],
                    [
                        'type' => 'section',
                        'title' => 'Section 1',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 1 in Section 1.',
                            ],
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 2 in Section 1.',
                            ],
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 3 in Section 1.',
                            ],
                            [
                                'type' => 'section',
                                'title' => 'Section 1.1',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'text' => 'Paragraph 1 in Section 1.1.',
                                    ],
                                    [
                                        'type' => 'quote',
                                        'text' => [
                                            [
                                                'type' => 'paragraph',
                                                'text' => 'Blockquote 1 in Section 1.1.',
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'paragraph',
                                        'text' => 'Paragraph 2 in Section 1.1.',
                                    ],
                                    [
                                        'type' => 'code',
                                        'code' => $this->lines([
                                            'Code sample 1 line 1 in Section 1.1.',
                                            'Code sample 1 line 2 in Section 1.1.',
                                        ], 2),
                                    ],
                                ],
                            ],
                            [
                                'type' => 'section',
                                'title' => 'Section 1.2',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'text' => 'Paragraph 1 in Section 1.2.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'title' => 'Section 2',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 1 in Section 2.',
                            ],
                            [
                                'type' => 'table',
                                'tables' => [
                                    '<table><tr><td>Table 1 in Section 2.</td></tr></table>',
                                ],
                            ],
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 2 in Section 2.',
                            ],
                        ],
                    ],
                ],
                $this->lines([
                    'Paragraph 1.',
                    '# Section 1',
                    'Paragraph 1 in Section 1.',
                    'Paragraph 2 in Section 1.',
                    'Paragraph 3 in Section 1.',
                    '## Section 1.1',
                    'Paragraph 1 in Section 1.1.',
                    '> Blockquote 1 in Section 1.1.',
                    'Paragraph 2 in Section 1.1.',
                    '```',
                    'Code sample 1 line 1 in Section 1.1.',
                    'Code sample 1 line 2 in Section 1.1.',
                    '```',
                    '## Section 1.2',
                    'Paragraph 1 in Section 1.2.',
                    '# Section 2',
                    'Paragraph 1 in Section 2.',
                    '<table><tr><td>Table 1 in Section 2.</td></tr></table>',
                    'Paragraph 2 in Section 2.',
                ], 2),
            ],
            'offset hierarchy' => [
                [
                    [
                        'type' => 'paragraph',
                        'text' => 'Paragraph 1.',
                    ],
                    [
                        'type' => 'section',
                        'title' => 'Section 1',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 1 in Section 1.',
                            ],
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 2 in Section 1.',
                            ],
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 3 in Section 1.',
                            ],
                            [
                                'type' => 'section',
                                'title' => 'Section 1.1',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'text' => 'Paragraph 1 in Section 1.1.',
                                    ],
                                    [
                                        'type' => 'paragraph',
                                        'text' => 'Paragraph 2 in Section 1.1.',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'section',
                                'title' => 'Section 1.2',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'text' => 'Paragraph 1 in Section 1.2.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'title' => 'Section 2',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 1 in Section 2.',
                            ],
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 2 in Section 2.',
                            ],
                        ],
                    ],
                ],
                $this->lines([
                    'Paragraph 1.',
                    '## Section 1',
                    'Paragraph 1 in Section 1.',
                    'Paragraph 2 in Section 1.',
                    'Paragraph 3 in Section 1.',
                    '### Section 1.1',
                    'Paragraph 1 in Section 1.1.',
                    'Paragraph 2 in Section 1.1.',
                    '### Section 1.2',
                    'Paragraph 1 in Section 1.2.',
                    '## Section 2',
                    'Paragraph 1 in Section 2.',
                    'Paragraph 2 in Section 2.',
                ], 2),
            ],
            'first section not primary' => [
                [
                    [
                        'type' => 'paragraph',
                        'text' => 'Paragraph 1.',
                    ],
                    [
                        'type' => 'section',
                        'title' => 'Section 1',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 1 in Section 1.',
                            ],
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 2 in Section 1.',
                            ],
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 3 in Section 1.',
                            ],
                            [
                                'type' => 'section',
                                'title' => 'Section 1.1',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'text' => 'Paragraph 1 in Section 1.1.',
                                    ],
                                    [
                                        'type' => 'paragraph',
                                        'text' => 'Paragraph 2 in Section 1.1.',
                                    ],
                                ],
                            ],
                            [
                                'type' => 'section',
                                'title' => 'Section 1.2',
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'text' => 'Paragraph 1 in Section 1.2.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'title' => 'Section 2',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 1 in Section 2.',
                            ],
                            [
                                'type' => 'paragraph',
                                'text' => 'Paragraph 2 in Section 2.',
                            ],
                        ],
                    ],
                ],
                $this->lines([
                    'Paragraph 1.',
                    '## Section 1',
                    'Paragraph 1 in Section 1.',
                    'Paragraph 2 in Section 1.',
                    'Paragraph 3 in Section 1.',
                    '### Section 1.1',
                    'Paragraph 1 in Section 1.1.',
                    'Paragraph 2 in Section 1.1.',
                    '### Section 1.2',
                    'Paragraph 1 in Section 1.2.',
                    '# Section 2',
                    'Paragraph 1 in Section 2.',
                    'Paragraph 2 in Section 2.',
                ], 2),
            ],
        ];
    }

    private function lines(array $lines, $breaks = 1)
    {
        return implode(str_repeat(PHP_EOL, $breaks), $lines);
    }

    private function createDocument(string $markdown = '') : Document
    {
        return $this->docParser->parse($markdown);
    }
}
