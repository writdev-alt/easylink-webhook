<?php

namespace App\Services;

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;

class ElasticsearchService
{
    protected $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()->setHosts([config('services.elk.host_ip')])->setBasicAuthentication(config('services.elk.username'), config('services.elk.password'))->setSSLVerification(false)->build();
    }

    /**
     * Test the connection to the Elasticsearch server.
     *
     * @return string
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function testConnection(): string
    {
        $response = $this->client->ping();
        return $response ? 'Connection successful' : 'Connection failed';
    }

    /**
     * Delete an index in Elasticsearch.
     *
     * @param string $indexName
     * @return array
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function deleteIndex(string $indexName)
    {
        $params = [
            'index' => $indexName
        ];

        return $this->client->indices()->delete($params);
    }

    /**
     * Check if an index exists in Elasticsearch.
     *
     * @param string $indexName
     * @return bool
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function indexExists(string $indexName): bool
    {
        return $this->client->indices()->exists(['index' => $indexName])->asBool();
    }

    /**
     * Create an index in Elasticsearch with the given name and default settings.
     *
     * @param string $indexName
     * @return array
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function createIndex(string $indexName)
    {
        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0
                ],
                'mappings' => [
                    'properties' => [
                        'title' => [
                            'type' => 'text'
                        ],
                        'content' => [
                            'type' => 'text'
                        ]
                    ]
                ]
            ]
        ];

        return $this->client->indices()->create($params);
    }

    /**
     * Populate an index with the given data.
     *
     * @param string $indexName
     * @param array $data
     * @return array
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function populateIndex(string $indexName, array $data)
    {
        $params = [
            'index' => $indexName,
            'body' => $data
        ];

        return $this->client->index($params);
    }

    /**
     * Verify if a document with the given ID exists in the specified index.
     *
     * @param string $index
     * @param string $id
     * @return array
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    private function verifyExists(string $index, string $id)
    {
        $data = $this->client->search(['index' => $index,
            'body' => ['query' => ['bool' => ['must' => ['term' => ['id' => $id]]]]]
        ]);
        return $data['hits']['hits'];
    }

    /**
     * Perform a bulk index operation with the given data.
     *
     * @param string $indexName
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function bulkIndexData(string $indexName, array $data): array
    {
        $params = ['body' => []];

        foreach ($data as $item) {
            $elastic_prop = $this->verifyExists($indexName, $item['id']);

            if (!count($elastic_prop)) {
                $params['body'][] = [
                    'create' => [
                        '_index' => $indexName
                    ]
                ];

                $params['body'][] = $item;
            } else {
                $params['body'][] = [
                    'update' => [
                        '_index' => $indexName,
                        '_id' => $elastic_prop[0]['_id'],
                    ]
                ];

                $params['body'][] = ['doc' => $item];
            }
        }

        if (!empty($params['body'])) {
            $response = $this->client->bulk($params);

            if (isset($response['errors']) && $response['errors']) {
                throw new \Exception('Bulk operation failed: ' . json_encode($response['items']));
            }
        }

        return ['message' => 'Bulk operation successful'];
    }

    /**
     * Get paginated data from the specified index.
     *
     * @param string $indexName
     * @param int $page
     * @param int $pageSize
     * @return array|string
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function getPaginatedIndexData(string $indexName, int $page = 1, int $pageSize = 10): array|string
    {
        $from = ($page - 1) * $pageSize;

        $params = [
            'index' => $indexName,
            'body' => [
                'from' => $from,
                'size' => $pageSize,
                'query' => [
                    'match_all' => new \stdClass()
                ]
            ]
        ];

        $response = $this->client->search($params);

        if (isset($response['hits']['hits'])) {
            return [
                'total' => $response['hits']['total']['value'],
                'data' => $response['hits']['hits'],
                'current_page' => $page,
                'per_page' => $pageSize
            ];
        }

        return 'No documents found';
    }

    /**
     * Get data from the specified index by document ID.
     *
     * @param string $indexName
     * @param string $id
     * @return array|string
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function getIndexData(string $indexName, string $id)
    {
        $params = [
            'index' => $indexName,
            'body' => [
                'query' => [
                    'match' => [
                        '_id' => $id
                    ]
                ]
            ]
        ];

        $response = $this->client->search($params);

        if (isset($response['hits']['hits'][0])) {
            return $response['hits']['hits'][0];
        }

        return 'Document not found';
    }


}
