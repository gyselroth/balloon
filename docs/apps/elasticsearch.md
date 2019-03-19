## Overview

Balloon.App.Elasticsearch is a core app and already shipped and enabled by default.
Elasticsearch gets used to offer fulltext search. Documents are stored by the internal balloon server directly and no additional tools are required.
The elasticsearch plugin ingest-attachment is required to provide blob indexing, see [balloon server deployment](getting-started).

## Indexes

`ballooncli uprade` automatically creates the required indexes `nodes` and `blobs`.

## Custom index settings

The indices get created with settings/mappings from index.json (`src/app/Balloon.App.Elasticsearch/index.json`). Those settings may
be changed according your needs. The default settings may work well for languages such as English.
Note that the path to index.json may also be set in the configuration within `Balloon\App\Elasticsearch\Migration\Delta\Installation`.

### German

If you need fulltextsearch for languages such as german you may consider an elasticsearch plugin like [elasticsearch-analysis-decompound](https://github.com/jprante/elasticsearch-analysis-decompound).
For example you may use this plugin with the following configuration.

Note that you will need to recreate the indices if you want to apply a new index.json:
(You have to manually drop the elasticsearch indices first if they already exists)

```sh
ballooncli upgrade -f -i -vvvv 
ballooncli elasticsearch reindex -vvv
```

```json
{
  "blobs" : {
    "settings": {
        "analysis": {
           "filter": {
               "decomp":{
                  "type" : "decompound"
              }
          },
           "analyzer": {
               "decomp": {
                   "type": "custom",
                   "tokenizer" : "standard",
                   "filter" : [
                        "decomp",
                        "unique",
                        "german_normalization",
                        "lowercase"
                   ]
               }
           }
        }
    },
    "mappings" : {
      "_doc" : {
        "properties" : {
          "attachment" : {
            "properties" : {
              "author" : {
                "type" : "text",
                "fields" : {
                  "keyword" : {
                    "type" : "keyword",
                    "ignore_above" : 256
                  }
                }
              },
              "content" : {
                "type" : "text",
                "analyzer" : "decomp",
                "fields" : {
                  "keyword" : {
                    "type" : "keyword",
                    "ignore_above" : 256
                  }
                }
              },
              "content_length" : {
                "type" : "long"
              },
              "content_type" : {
                "type" : "text",
                "fields" : {
                  "keyword" : {
                    "type" : "keyword",
                    "ignore_above" : 256
                  }
                }
              },
              "date" : {
                "type" : "date"
              },
              "language" : {
                "type" : "text",
                "fields" : {
                  "keyword" : {
                    "type" : "keyword",
                    "ignore_above" : 256
                  }
                }
              },
              "title" : {
                "type" : "text",
                "fields" : {
                  "keyword" : {
                    "type" : "keyword",
                    "ignore_above" : 256
                  }
                }
              }
            }
          },
          "content" : {
            "type" : "text",
            "analyzer" : "decomp",
            "fields" : {
              "keyword" : {
                "type" : "keyword",
                "ignore_above" : 256
              }
            }
          },
          "md5" : {
            "type" : "text",
            "fields" : {
              "keyword" : {
                "type" : "keyword",
                "ignore_above" : 256
              }
            }
          },
          "metadata" : {
            "properties" : {
              "ref" : {
                "properties" : {
                  "id" : {
                    "type" : "text",
                    "fields" : {
                      "keyword" : {
                        "type" : "keyword",
                        "ignore_above" : 256
                      }
                    }
                  },
                  "owner" : {
                    "type" : "text",
                    "fields" : {
                      "keyword" : {
                        "type" : "keyword",
                        "ignore_above" : 256
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  },
  "nodes" : {
    "settings": {
        "analysis": {
           "filter": {
               "decomp":{
                  "type" : "decompound"
              }
          },
           "analyzer": {
               "decomp": {
                   "type": "custom",
                   "tokenizer" : "standard",
                   "filter" : [
                        "decomp",
                        "unique",
                        "german_normalization",
                        "lowercase"
                   ]
               }
           }
        }
    },
    "mappings" : {
      "_doc" : {
        "properties" : {
          "changed" : {
            "type" : "date"
          },
          "created" : {
            "type" : "date"
          },
          "deleted" : {
            "type" : "date"
          },
          "directory" : {
            "type" : "boolean"
          },
          "hash" : {
            "type" : "text",
            "fields" : {
              "keyword" : {
                "type" : "keyword",
                "ignore_above" : 256
              }
            }
          },
          "meta" : {
            "properties" : {
              "color" : {
                "type" : "text",
                "fields" : {
                  "keyword" : {
                    "type" : "keyword",
                    "ignore_above" : 256
                  }
                }
              },
              "tags" : {
                "type" : "text",
                "fields" : {
                  "keyword" : {
                    "type" : "keyword",
                    "ignore_above" : 256
                  }
                }
              }
            }
          },
          "mime" : {
            "type" : "text",
            "fields" : {
              "keyword" : {
                "type" : "keyword",
                "ignore_above" : 256
              }
            }
          },
          "name" : {
            "type" : "text",
            "analyzer" : "decomp",
            "fields" : {
              "keyword" : {
                "type" : "keyword",
                "ignore_above" : 256
              }
            }
          },
          "owner" : {
            "type" : "text",
            "fields" : {
              "keyword" : {
                "type" : "keyword",
                "ignore_above" : 256
              }
            }
          },
          "parent" : {
            "type" : "text",
            "fields" : {
              "keyword" : {
                "type" : "keyword",
                "ignore_above" : 256
              }
            }
          },
          "readonly" : {
            "type" : "boolean"
          },
          "reference" : {
            "type" : "boolean"
          },
          "share" : {
            "type" : "boolean"
          },
          "shared" : {
            "type" : "text",
            "fields" : {
              "keyword" : {
                "type" : "keyword",
                "ignore_above" : 256
              }
            }
          },
          "size" : {
            "type" : "long"
          },
          "version" : {
            "type" : "long"
          }
        }
      }
    }
  }
}

Note that this plugin depends on the elasticsearch version which usually lacks behind, v6.2.2 is supported by now (March 2019):
```yaml
elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:6.2.2
    entrypoint:
        - /bin/sh
        - -c
        - "elasticsearch-plugin list | grep ingest-attachment || elasticsearch-plugin install ingest-attachment --batch && elasticsearch-plugin list | grep elasticsearch-analysis-decompound || elasticsearch-plugin install http://xbib.org/repository/org    /xbib/elasticsearch/plugin/elasticsearch-analysis-decompound/6.2.2.0/elasticsearch-analysis-decompound-6.2.2.0.zip --batch && docker-entrypoint.sh"
```
