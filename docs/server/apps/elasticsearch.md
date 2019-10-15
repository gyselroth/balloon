## Overview

Balloon.App.Elasticsearch is a core app and already shipped and enabled by default.
Elasticsearch gets used to offer fulltext search. Documents are stored by the internal balloon server directly and no additional tools are required.
The elasticsearch plugin ingest-attachment is required to provide blob indexing, see [balloon server installation](/server/installation).

## Indexes

`ballooncli uprade` automatically creates the required indexes `nodes` and `blobs`.

## Custom index settings

The indices get created with settings/mappings from index.json (`src/app/Balloon.App.Elasticsearch/index.json`). Those settings may
be changed according your needs. The default settings may work well for languages such as English.
Note that the path to index.json may also be set in the configuration within `Balloon\App\Elasticsearch\Migration\Delta\Installation`.

### German

If you need fulltextsearch for languages such as german you may consider a custom elasticsearch configuration using [compound word lists](https://github.com/uschindler/german-decompounder).
For example you may use this plugin with the following configuration.

Note that you will need to recreate the indices if you want to apply a new index.json:
(You have to manually drop the elasticsearch indices first if they already exists)

```sh
ballooncli upgrade -f -i -d 'Balloon\App\Elasticsearch\Migration\Delta\Installation' -vvvv 
ballooncli elasticsearch reindex -vvv
```


```json
{
  "blobs" : {
    "settings": {
      "analysis": {
         "filter": {
            "german_decompounder": {
               "type": "hyphenation_decompounder",
               "word_list_path": "analysis/dictionary-de.txt",
               "hyphenation_patterns_path": "analysis/de_DR.xml",
               "only_longest_match": true,
               "min_subword_size": 4
            },
            "german_stemmer": {
               "type": "stemmer",
               "language": "light_german"
            }
         },
         "analyzer": {
            "german_decompound": {
               "type": "custom",
               "tokenizer": "standard",
               "filter": [
                  "lowercase",
                  "german_decompounder",
                  "german_normalization",
                  "german_stemmer"
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
                "analyzer" : "german_decompound",
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
            "analyzer" : "german_decompound",
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
            "german_decompounder": {
               "type": "hyphenation_decompounder",
               "word_list_path": "analysis/dictionary-de.txt",
               "hyphenation_patterns_path": "analysis/de_DR.xml",
               "only_longest_match": true,
               "min_subword_size": 4
            },
            "german_stemmer": {
               "type": "stemmer",
               "language": "light_german"
            }
         },
         "analyzer": {
            "german_decompound": {
               "type": "custom",
               "tokenizer": "standard",
               "filter": [
                  "lowercase",
                  "german_decompounder",
                  "german_normalization",
                  "german_stemmer"
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
            "analyzer" : "german_decompound",
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
```
