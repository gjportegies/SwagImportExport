<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Functional\Components\SwagImportExport\DbAdapters;

use Doctrine\DBAL\Connection;
use Shopware\Components\SwagImportExport\DbAdapters\ArticlesDbAdapter;
use SwagImportExport\Tests\Helper\DatabaseTestCaseTrait;

class ArticlesDbAdapterTest extends \PHPUnit_Framework_TestCase
{
    use DatabaseTestCaseTrait;

    /**
     * @return ArticlesDbAdapter
     */
    private function createArticleDbAdapter()
    {
        return new ArticlesDbAdapter();
    }

    public function test_write_should_throw_exception_if_records_are_empty()
    {
        $articlesDbAdapter = $this->createArticleDbAdapter();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Keine Artikel gefunden.');
        $articlesDbAdapter->write([]);
    }

    public function test_new_article_should_be_written_to_database()
    {
        $articlesDbAdapter = $this->createArticleDbAdapter();
        $newArticleRecord = [
            'article' => [
                [
                    'name' => 'Testartikel',
                    'orderNumber' => 'SW-99999',
                    'mainNumber' => 'SW-99999',
                    'supplierId' => 2,
                    'supplierName' => 'Feinbrennerei Sasse',
                    'taxId' => 1
                ]
            ]
        ];
        $articlesDbAdapter->write($newArticleRecord);

        /** @var Connection $dbalConnection */
        $dbalConnection = Shopware()->Container()->get('dbal_connection');
        $createdArticleDetail = $dbalConnection->executeQuery('SELECT * FROM s_articles_details WHERE orderNumber="SW-99999"')->fetchAll();
        $createdArticle = $dbalConnection->executeQuery('SELECT a.* FROM s_articles as a JOIN s_articles_details as d ON d.articleID = a.id WHERE d.orderNumber="SW-99999"')->fetchAll();

        $this->assertEquals($newArticleRecord['article'][0]['name'], $createdArticle[0]['name']);
        $this->assertEquals($newArticleRecord['article'][0]['taxId'], $createdArticle[0]['taxID']);
        $this->assertEquals($newArticleRecord['article'][0]['orderNumber'], $createdArticleDetail[0]['ordernumber']);
    }

    public function test_write_should_add_supplier_if_it_not_exists()
    {
        $articlesDbAdapter = $this->createArticleDbAdapter();

        $articleRecordWithNewSupplier = [
            'article' => [
                [
                    'name' => 'Testartikel',
                    'orderNumber' => 'SW-99999',
                    'mainNumber' => 'SW-99999',
                    'supplierName' => 'Test Supplier',
                    'taxId' => 1
                ]
            ]
        ];
        $articlesDbAdapter->write($articleRecordWithNewSupplier);

        $dbalConnection = Shopware()->Container()->get('dbal_connection');
        $createdArticleSupplier = $dbalConnection->executeQuery("SELECT * FROM s_articles_supplier WHERE name='Test Supplier'")->fetchAll();

        $this->assertEquals($articleRecordWithNewSupplier['article']['supplerName'], $createdArticleSupplier[0]['supplierName']);
    }

    public function test_write_article_without_supplier_throws_exception()
    {
        $articlesDbAdapter = $this->createArticleDbAdapter();
        $articleWithoutSupplier = [
            'article' => [
                [
                    'name' => 'Testartikel',
                    'orderNumber' => 'SW-99999',
                    'mainNumber' => 'SW-99999',
                    'taxId' => 1
                ]
            ]
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Hersteller für den Artikel SW-99999 nicht gefunden.');
        $articlesDbAdapter->write($articleWithoutSupplier);
    }

    public function test_new_image_should_fill_unprocessed_data_array()
    {
        $articlesDbAdapter = $this->createArticleDbAdapter();
        $records = [
            'article' => [
                0 => [
                    'orderNumber' => 'SW10006',
                    'mainNumber' => 'SW10006'
                ]
            ],
            'image' => [
                0 => [
                    'imageUrl' => 'file://' . realpath(dirname(__FILE__)) . '/../../../../Helper/ImportFiles/sw-icon_blue128.png',
                    'parentIndexElement' => 0
                ]
            ]
        ];
        $articlesDbAdapter->write($records);
        $unprocessedData = $articlesDbAdapter->getUnprocessedData();

        $this->assertTrue(!empty($unprocessedData));
        $this->assertEquals($records['article'][0]['orderNumber'], $unprocessedData['articlesImages']['default'][0]['ordernumber']);
        $this->assertEquals($records['image'][0]['imageUrl'], $unprocessedData['articlesImages']['default'][0]['image']);
    }

    public function test_existing_image_should_be_added_to_article()
    {
        $articlesDbAdapter = $this->createArticleDbAdapter();
        $records = [
            'article' => [
                0 => [
                    'orderNumber' => 'SW10006',
                    'mainNumber' => 'SW10006'
                ]
            ],
            'image' => [
                0 => [
                    'mediaId' => 6,
                    'path' => 'Muensterlaender_Lagerkorn',
                    'description' => 'testimport1',
                    'parentIndexElement' => 0
                ]
            ]
        ];
        $articlesDbAdapter->write($records);

        /** @var Connection $dbalConnection */
        $dbalConnection = Shopware()->Container()->get('dbal_connection');
        $articleId = $dbalConnection->executeQuery('SELECT articleID FROM s_articles_details WHERE orderNumber="SW10006"')->fetch(\PDO::FETCH_COLUMN);
        $image = $dbalConnection->executeQuery('SELECT * FROM s_articles_img WHERE img = "Muensterlaender_Lagerkorn" AND articleID = ?', [$articleId])->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals($articleId, $image['articleID']);
        $this->assertEquals($records['image'][0]['mediaId'], $image['media_id']);
        $this->assertEquals($records['image'][0]['description'], $image['description']);
        $this->assertEquals('jpg', $image['extension']);
    }

    public function test_read()
    {
        $articlesDbAdapter = $this->createArticleDbAdapter();
        $ids = [ 3 ];
        $result = $articlesDbAdapter->read($ids, $this->getColumns());

        $this->assertArrayHasKey('article', $result, "Could not fetch articles.");
        $this->assertArrayHasKey('price', $result, "Could not fetch article prices.");
        $this->assertArrayHasKey('image', $result, "Could not fetch article image prices.");
        $this->assertArrayHasKey('propertyValue', $result, "Could not fetch article property values.");
        $this->assertArrayHasKey('similar', $result, "Could not fetch similar articles.");
        $this->assertArrayHasKey('accessory', $result, "Could not fetch accessory articles.");
        $this->assertArrayHasKey('category', $result, "Could not fetch categories.");
        $this->assertArrayHasKey('translation', $result, "Could not fetch article translations.");
        $this->assertArrayHasKey('configurator', $result, "Could not fetch article configurators");
    }

    public function test_read_should_throw_exception_if_ids_are_empty()
    {
        $articlesDbAdapter = $this->createArticleDbAdapter();
        $columns = [ 'article' => 'article.id as articleId' ];
        $ids = [];

        $this->expectException(\Exception::class);
        $articlesDbAdapter->read($ids, $columns);
    }

    public function test_read_should_throw_exception_if_columns_are_empty()
    {
        $articlesDbAdapter = $this->createArticleDbAdapter();
        $columns = [];
        $ids = [ 1, 2, 3 ];

        $this->expectException(\Exception::class);
        $articlesDbAdapter->read($ids, $columns);
    }

    /**
     * @return array
     */
    private function getColumns()
    {
        return [
            'article' => [
                "article.id as articleId"
            ],
            'price' => [
                "prices.articleDetailsId as variantId"
            ],
            'image' => [
                "images.id as id"
            ],
            'propertyValues' => [
                "article.id as articleId"
            ],
            'similar' => [
                "similar.id as similarId"
            ],
            'accessory' => [
                "accessory.id as accessoryId"
            ],
            'configurator' => [
                "variant.id as variantId"
            ],
            'category' => [
                "categories.id as categoryId"
            ],
            'translation' => [
                "article.id as articleId"
            ]
        ];
    }
}
