<?php

namespace BlockshiftNetwork\SapB1Client;

use Illuminate\Http\Client\Response;

class SapB1Query extends ODataQuery
{
    public function __construct(
        protected SapB1Client $client,
        protected string $entity,
    ) {}

    /**
     * Execute the OData query and return the response (GET with filters).
     *
     * Usage: SapB1::query('BusinessPartners')->where('CardType', 'cCustomer')->get()
     */
    public function get(): Response
    {
        return $this->client->odataQuery($this->entity, $this);
    }

    /**
     * Find a single record by its primary key.
     *
     * Usage: SapB1::query('BusinessPartners')->find('C001')
     *        SapB1::query('Orders')->find(123)
     *
     * @param  string|int  $key  The primary key value.
     */
    public function find(string|int $key): Response
    {
        $endpoint = $this->entity . '(' . $this->formatKey($key) . ')';

        // Apply $select if specified, ignore filters/ordering/pagination
        $params = [];
        $array = $this->toArray();
        if (isset($array['$select'])) {
            $params['$select'] = $array['$select'];
        }

        return $this->client->get($endpoint, $params);
    }

    /**
     * Create a new record.
     *
     * Usage: SapB1::query('BusinessPartners')->create(['CardCode' => 'C001', 'CardName' => 'Acme'])
     *
     * @param  array<string, mixed>  $data  The record data.
     */
    public function create(array $data): Response
    {
        return $this->client->post($this->entity, $data);
    }

    /**
     * Update an existing record by its primary key (PATCH).
     *
     * When $replaceCollections is true, the header "B1S-ReplaceCollectionsOnPatch: true"
     * is sent, which tells SAP B1 to replace collection properties (e.g. DocumentLines)
     * instead of merging them. This is required when you need to remove or reorder lines.
     *
     * Usage: SapB1::query('Orders')->update(123, ['Comments' => 'Updated'])
     *        SapB1::query('Orders')->update(123, ['DocumentLines' => [...]], replaceCollections: true)
     *
     * @param  string|int  $key   The primary key value.
     * @param  array<string, mixed>  $data  The fields to update.
     * @param  bool  $replaceCollections  Send B1S-ReplaceCollectionsOnPatch header.
     */
    public function update(string|int $key, array $data, bool $replaceCollections = false): Response
    {
        $endpoint = $this->entity . '(' . $this->formatKey($key) . ')';

        if ($replaceCollections) {
            $this->client->withHeaders(['B1S-ReplaceCollectionsOnPatch' => 'true']);
        }

        return $this->client->patch($endpoint, $data);
    }

    /**
     * Delete a record by its primary key.
     *
     * Usage: SapB1::query('BusinessPartners')->delete('C001')
     *        SapB1::query('Orders')->delete(123)
     *
     * @param  string|int  $key  The primary key value.
     */
    public function delete(string|int $key): Response
    {
        $endpoint = $this->entity . '(' . $this->formatKey($key) . ')';

        return $this->client->delete($endpoint);
    }

    /**
     * Format a primary key for use in SAP B1 Service Layer URLs.
     *
     * String keys: Entity('C001')
     * Numeric keys: Entity(123)
     */
    protected function formatKey(string|int $key): string
    {
        if (is_int($key)) {
            return (string) $key;
        }

        return "'{$key}'";
    }
}
