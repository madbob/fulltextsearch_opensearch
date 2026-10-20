# Full text search - OpenSearch

_Full Text Search - OpenSearch_ is an extension to the _Full Text Search_ framework

It allows you to index your content into an OpenSearch instance.

## Setup

To index files and documents, you need to install the [ingest-attachment plugin](https://docs.opensearch.org/latest/install-and-configure/additional-plugins/ingest-attachment-plugin/) before starting indexing.

The proper OpenSearch pipeline is automatically created when the local index is inited.

## Configuration

There are many settings available; some are accessible through the web interface, others are intentionally accessible only by CLI.

```
php occ config:app:get fulltextsearch_opensearch CONFIG_NAME
php occ config:app:set --value NEW_VALUE fulltextsearch_opensearch CONFIG_NAME
```

Most of them are used while initing a new index on OpenSearch, so changing their value while using an existing collection has no effect.

### fields_limit

Maximum number of fields in the index map.

https://docs.opensearch.org/latest/mappings/mapping-explosion/

Default: 10000

### analyzer_filter

Comma-separated list of parameters for the analyzer's filter. To be used for fine tuning accordingly to the language of documents and data on the instance.

https://docs.opensearch.org/latest/analyzers/token-filters/index/

Default: "lowercase, stop, kstem"

### max_token_length

To limit the length of accepted and indexed tokens, and skip hashes, encoded data or other informations not useful while searching.

https://docs.opensearch.org/latest/analyzers/token-filters/length/

Default: 40

### index_codec

Can be used to adopt a compressed format for the index, in order to save some disk space.

https://docs.opensearch.org/latest/im-plugin/index-codecs/

Default: "default"
