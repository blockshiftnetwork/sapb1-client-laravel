<?php

namespace BlockshiftNetwork\SapB1Client;

use Illuminate\Http\Client\Response;

class QueryBuilder extends ODataQuery
{
    protected SapB1Client $client;

    protected string $entity;

    public function __construct(SapB1Client $client, string $entity)
    {
        $this->client = $client;
        $this->entity = $entity;
    }

    /**
     * Execute the query and return the response.
     */
    public function run(): Response
    {
        return $this->client->odataQuery($this->entity, $this);
    }

    /**
     * Alias for run() - more familiar for Eloquent users.
     */
    public function get(string|int|null $id = null): Response
    {
        if ($id !== null) {
            return $this->find($id);
        }

        return $this->run();
    }

    public function find(string | int $id): Response
    {
        $this->entity = $this->entity.'('.$id.')';

        return $this->run();
    }
}
