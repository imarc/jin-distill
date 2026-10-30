<?php

namespace JinDistill\Tests;

use JinDistill\JinDocument;
use JinDistill\Support\Arr;
use PHPUnit\Framework\TestCase;

final class JinDocumentTest extends TestCase
{
    public function testItStoresDataDirectivesMetadataAndPath(): void
    {
        $document = new JinDocument(
            data: ['form' => ['name' => 'CPA']],
            directives: ['extends' => 'base.jin', 'without' => ['form.name']],
            metadata: ['form.name' => ['leadingComments' => ['Name'], 'inlineComment' => 'inline']],
            path: '/tmp/forms/cpa.jin'
        );

        self::assertSame(['form' => ['name' => 'CPA']], $document->data);
        self::assertSame('base.jin', $document->directives['extends']);
        self::assertSame(['form.name'], $document->directives['without']);
        self::assertSame('/tmp/forms/cpa.jin', $document->path);
    }

    public function testArrayHelpersDeletePathsAndMergeAssociativeArrays(): void
    {
        $data = [
            'form' => [
                'name' => 'Default',
                'fields' => [
                    'person' => [
                        'firstName' => true,
                        'avatar' => false,
                    ],
                ],
            ],
        ];

        Arr::delete($data, 'form.fields.person.avatar');

        self::assertArrayNotHasKey('avatar', $data['form']['fields']['person']);

        $merged = Arr::mergeDistinct($data, [
            'form' => [
                'name' => 'CPA',
                'fields' => [
                    'person' => [
                        'lastName' => true,
                    ],
                ],
            ],
        ]);

        self::assertSame('CPA', $merged['form']['name']);
        self::assertTrue($merged['form']['fields']['person']['firstName']);
        self::assertTrue($merged['form']['fields']['person']['lastName']);
    }
}
