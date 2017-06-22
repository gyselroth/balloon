define({ "api": [
  {
    "type": "delete",
    "url": "/api/v1/app/office/session",
    "title": "Delete session",
    "name": "delete",
    "group": "App_Office",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Delete a running session. If more members are active in the requested session than only the membership gets removed. The session gets completely removed if only one member exists.</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "session_id",
            "description": "<p>The session id to delete</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "access_token",
            "description": "<p>Access token</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XDELETE \"https://SERVER/api/v1/app/office/session?session_id=58a18a4ca271f962af6fdbc4&access_token=97223329239823bj223232323\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 OK",
          "type": "json"
        }
      ]
    },
    "version": "0.0.0",
    "filename": "/home/users/raffael.sahli/github/balloon/src/app/Office/src/lib/Rest/v1/Session.php",
    "groupTitle": "App_Office",
    "sampleRequest": [
      {
        "url": " /api/v1/app/office/session"
      }
    ]
  },
  {
    "type": "get",
    "url": "/api/v1/app/office/document?id=:id",
    "title": "Get document",
    "name": "get",
    "group": "App_Office",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Retreive office document</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/app/office/document/544627ed3c58891f058b4611\"\ncurl -XGET \"https://SERVER/api/v1/app/office/document?id=544627ed3c58891f058b4611\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\": 200,\n     \"data\": {\n                \"loleaflet\": \"https:\\/\\/officeserver:9980\\/loleaflet\\/dist\\/loleaflet.html\",\n                \"sessions\": []\n            }\n}",
          "type": "json"
        }
      ]
    },
    "version": "0.0.0",
    "filename": "/home/users/raffael.sahli/github/balloon/src/app/Office/src/lib/Rest/v1/Document.php",
    "groupTitle": "App_Office",
    "sampleRequest": [
      {
        "url": " /api/v1/app/office/document?id=:id"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/app/office/wopi/document",
    "title": "Get document sesssion information",
    "name": "get",
    "group": "App_Office",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get document session information including document owner, session user and document size</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>The document id</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "access_token",
            "description": "<p>An access token to access the document</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/app/office/wopi/document/58a18a4ca271f962af6fdbc4?access_token=aae366363ee743412abb\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     [***]\n}",
          "type": "json"
        }
      ]
    },
    "version": "0.0.0",
    "filename": "/home/users/raffael.sahli/github/balloon/src/app/Office/src/lib/Rest/v1/Wopi/Document.php",
    "groupTitle": "App_Office",
    "sampleRequest": [
      {
        "url": " /api/v1/app/office/wopi/document"
      }
    ]
  },
  {
    "type": "get",
    "url": "/api/v1/app/office/wopi/document/contents",
    "title": "Get document contents",
    "name": "getContents",
    "group": "App_Office",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get document contents</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>The document id</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "access_token",
            "description": "<p>An access token to access the document</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) Exampl:",
        "content": "curl -XGET \"https://SERVER/api/v1/app/office/document/58a18a4ca271f962af6fdbaa/contents?access_token=aae366363ee743412abb\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK",
          "type": "binary"
        }
      ]
    },
    "version": "0.0.0",
    "filename": "/home/users/raffael.sahli/github/balloon/src/app/Office/src/lib/Rest/v1/Wopi/Document.php",
    "groupTitle": "App_Office",
    "sampleRequest": [
      {
        "url": " /api/v1/app/office/wopi/document/contents"
      }
    ]
  },
  {
    "type": "post",
    "url": "/api/v1/app/office/session",
    "title": "Create session",
    "name": "post",
    "group": "App_Office",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Create new session for a document</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/app/office/session?id=58a18a4ca271f962af6fdbc4\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 201 Created\n{\n     \"status\": 201,\n     \"data\": {\n         \"id\": \"544627ed3c58891f058bbbaa\"\n         \"access_token\": \"544627ed3c58891f058b4622\",\n         \"access_token_ttl\": \"1486989000\"\n      }\n}",
          "type": "json"
        }
      ]
    },
    "version": "0.0.0",
    "filename": "/home/users/raffael.sahli/github/balloon/src/app/Office/src/lib/Rest/v1/Session.php",
    "groupTitle": "App_Office",
    "sampleRequest": [
      {
        "url": " /api/v1/app/office/session"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "post",
    "url": "/api/v1/app/office/wopi/document/contents",
    "title": "Save document contents",
    "name": "postContents",
    "group": "App_Office",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Save document contents</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>The document id</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "access_token",
            "description": "<p>An access token to access the document</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/app/office/wopi/document/58a18a4ca271f962af6fdbaa/contents?access_token=aae366363ee743412abb\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\": 200,\n     \"data\": true\n}",
          "type": "json"
        }
      ]
    },
    "version": "0.0.0",
    "filename": "/home/users/raffael.sahli/github/balloon/src/app/Office/src/lib/Rest/v1/Wopi/Document.php",
    "groupTitle": "App_Office",
    "sampleRequest": [
      {
        "url": " /api/v1/app/office/wopi/document/contents"
      }
    ]
  },
  {
    "type": "post",
    "url": "/api/v1/app/office/session",
    "title": "Join session",
    "name": "postJoin",
    "group": "App_Office",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Create new session for a document</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "session_id",
            "description": "<p>The session id to join to</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/app/office/session/join?session_id=58a18a4ca271f962af6fdbc4\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\": 201,\n     \"data\": {\n         \"access_token\": \"544627ed3c58891f058b4622\",\n         \"access_token_ttl\": \"1486989000\"\n     }\n}",
          "type": "json"
        }
      ]
    },
    "version": "0.0.0",
    "filename": "/home/users/raffael.sahli/github/balloon/src/app/Office/src/lib/Rest/v1/Session.php",
    "groupTitle": "App_Office",
    "sampleRequest": [
      {
        "url": " /api/v1/app/office/session"
      }
    ]
  },
  {
    "type": "put",
    "url": "/api/v1/app/office/document",
    "title": "Create new empty document",
    "name": "put",
    "group": "App_Office",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Create new document from an existing office document template, option type has to be one of the follwing:</p> <ul> <li>xlsx  =&gt; &quot;Office Open XML Spreadsheet&quot;,</li> <li>xls   =&gt; &quot;Microsoft Excel 97-2003&quot;,</li> <li>xlt   =&gt; &quot;Microsoft Excel 97-2003 Template&quot;,</li> <li>csv   =&gt; &quot;Text CSV&quot;,</li> <li>ods   =&gt; &quot;ODF Spreadsheet&quot;,</li> <li>ots   =&gt; &quot;ODF Spreadsheet Template&quot;,</li> <li>docx  =&gt; &quot;Office Open XML Text&quot;,</li> <li>doc   =&gt; &quot;Microsoft Word 97-2003&quot;,</li> <li>dot   =&gt; &quot;Microsoft Word 97-2003 Template&quot;,</li> <li>odt   =&gt; &quot;ODF Textdocument&quot;,</li> <li>ott   =&gt; &quot;ODF Textdocument Template&quot;,</li> <li>pptx  =&gt; &quot;Office Open XML Presentation&quot;,</li> <li>ppt   =&gt; &quot;Microsoft Powerpoint 97-2003&quot;,</li> <li>potm  =&gt; &quot;Microsoft Powerpoint 97-2003 Template&quot;,</li> <li>odp   =&gt; &quot;ODF Presentation&quot;,</li> <li>otp   =&gt; &quot;ODF Presentation Template&quot;</li> </ul>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "name",
            "description": "<p>The name of the new document</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "collection",
            "description": "<p>Parent collection id (If none  given, the document will be placed under root)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "type",
            "description": "<p>Office document file type</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "attributes",
            "description": "<p>Node attributes</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPUT \"https://SERVER/api/v1/app/office/document?type=xlsx\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 201 Created\n{\n     \"status\": 201,\n     \"data\": \"544627ed3c58891f058b4611\"\n}",
          "type": "json"
        }
      ]
    },
    "version": "0.0.0",
    "filename": "/home/users/raffael.sahli/github/balloon/src/app/Office/src/lib/Rest/v1/Document.php",
    "groupTitle": "App_Office",
    "sampleRequest": [
      {
        "url": " /api/v1/app/office/document"
      }
    ]
  },
  {
    "type": "post",
    "url": "/api/v1/collection/share?id=:id",
    "title": "Create share",
    "version": "1.0.6",
    "group": "Node_Collection",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Create a new share from an existing collection</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/collection/share?id=212323eeffe2322344224452&pretty\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "POST Parameter": [
          {
            "group": "POST Parameter",
            "type": "object",
            "optional": false,
            "field": "acl",
            "description": "<p>Share ACL</p>"
          },
          {
            "group": "POST Parameter",
            "type": "object[]",
            "optional": false,
            "field": "acl.user",
            "description": "<p>User ACL rules</p>"
          },
          {
            "group": "POST Parameter",
            "type": "string",
            "optional": false,
            "field": "acl.user.user",
            "description": "<p>Username which should match ACL rule</p>"
          },
          {
            "group": "POST Parameter",
            "type": "string",
            "optional": false,
            "field": "acl.user.priv",
            "description": "<p>Permission to access share, could be on of the following:</br> rw - READ/WRITE </br> r - READONLY </br> w - WRITEONLY </br> d - DENY </br></p>"
          },
          {
            "group": "POST Parameter",
            "type": "object[]",
            "optional": false,
            "field": "acl.group",
            "description": "<p>Group ACL rules</p>"
          },
          {
            "group": "POST Parameter",
            "type": "string",
            "optional": false,
            "field": "acl.priv",
            "description": "<p>Permission to access share, see possible permissions above</p>"
          }
        ],
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "201 Created": [
          {
            "group": "201 Created",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status code</p>"
          },
          {
            "group": "201 Created",
            "type": "boolean",
            "optional": false,
            "field": "data",
            "description": ""
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response (Created or Modified Share):",
          "content": "HTTP/1.1 201 Created\n{\n     \"status\":201,\n     \"data\": true\n}",
          "type": "json"
        },
        {
          "title": "Success-Response (Removed share):",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Collection.php",
    "groupTitle": "Node_Collection",
    "name": "PostApiV1CollectionShareIdId",
    "sampleRequest": [
      {
        "url": " /api/v1/collection/share?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "delete",
    "url": "/api/v1/collection/share?id=:id",
    "title": "Delete share",
    "version": "1.0.6",
    "name": "deleteShare",
    "group": "Node_Collection",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Does only remove sharing options and transform a share back into a normal collection. There won't be any data loss after this action. All existing references would be removed automatically.</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XDELETE \"https://SERVER/api/v1/collection/share?id=212323eeffe2322344224452\"\ncurl -XDELETE \"https://SERVER/api/v1/collection/212323eeffe2322344224452/share\"\ncurl -XDELETE \"https://SERVER/api/v1/collection/share?p=/absolute/path/to/my/collection\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Collection.php",
    "groupTitle": "Node_Collection",
    "sampleRequest": [
      {
        "url": " /api/v1/collection/share?id=:id"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/collection/children",
    "title": "Get children",
    "version": "1.0.6",
    "name": "getChildren",
    "group": "Node_Collection",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Find all children of a collection</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/collection/children?id=212323eeffe2322344224452&pretty\"\ncurl -XGET \"https://SERVER/api/v1/collection/212323eeffe2322344224452/children?pretty&deleted=0\"\ncurl -XGET \"https://SERVER/api/v1/collection/children?p=/absolute/path/to/my/collection&deleted=1\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": true,
            "field": "attributes",
            "description": "<p>Filter node attributes</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": true,
            "field": "filter",
            "description": "<p>Filter nodes</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "deleted",
            "defaultValue": "0",
            "description": "<p>Wherever include deleted nodes or not, possible values:</br></p> <ul> <li>0 Exclude deleted</br></li> <li>1 Only deleted</br></li> <li>2 Include deleted</br></li> </ul>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Children</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Unique node id</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.name",
            "description": "<p>Name</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.hash",
            "description": "<p>MD5 content checksum (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.meta",
            "description": "<p>Extended meta attributes</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.description",
            "description": "<p>UTF-8 Text Description</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.color",
            "description": "<p>Color Tag (HEX) (Like: #000000)</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.author",
            "description": "<p>Author</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.mail",
            "description": "<p>Mail contact address</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.license",
            "description": "<p>License</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.copyright",
            "description": "<p>Copyright string</p>"
          },
          {
            "group": "200 OK",
            "type": "string[]",
            "optional": false,
            "field": "data.meta.tags",
            "description": "<p>Search Tags</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.size",
            "description": "<p>Size in bytes (Only file node), number of children if collection</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.mime",
            "description": "<p>Mime type</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.sharelink",
            "description": "<p>Is node shared?</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.version",
            "description": "<p>File version (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "mixed",
            "optional": false,
            "field": "data.deleted",
            "description": "<p>Is boolean false if not deleted, if deleted it contains a deleted timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.changed",
            "description": "<p>Changed timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.created",
            "description": "<p>Created timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.share",
            "description": "<p>Node is shared</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.directory",
            "description": "<p>Is node a collection or a file?</p>"
          }
        ],
        "200 OK - additional attributes": [
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.thumbnail",
            "description": "<p>Id of preview (file node only)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.access",
            "description": "<p>Access if node is shared, one of r/rw/w</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.shareowner",
            "description": "<p>Username of the share owner</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.parent",
            "description": "<p>ID of the parent node</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.path",
            "description": "<p>Absolute node path</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.filtered",
            "description": "<p>Node is filtered (usually only a collection)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.readonly",
            "description": "<p>Node is readonly</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "object[]",
            "optional": false,
            "field": "data.history",
            "description": "<p>Get file history (file node only)</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\":200,\n     \"data\": [{..}, {...}] //Shorted\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Collection.php",
    "groupTitle": "Node_Collection",
    "sampleRequest": [
      {
        "url": " /api/v1/collection/children"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/collection/share?id=:id",
    "title": "Get Share parameters",
    "version": "1.0.6",
    "name": "getShare",
    "group": "Node_Collection",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get share parameters</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/collection/share?id=212323eeffe2322344224452&pretty\"\ncurl -XGET \"https://SERVER/api/v1/collection/212323eeffe2322344224452/share?pretty\"\ncurl -XGET \"https://SERVER/api/v1/collection/share?p=/absolute/path/to/my/collection&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Share ACL with roles and permissions</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.type",
            "description": "<p>Either group or user</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>A unique role identifier</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.name",
            "description": "<p>Could be the same as id, but don't have to (human readable name)</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.priv",
            "description": "<p>Permission to access share</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\":200,\n     \"data\":[\n         {\n             \"type\":\"user\",\n             \"id\":\"peter.meier\",\n             \"name\":\"peter.meier\",\n             \"priv\":\"rw\"\n         }\n     ]\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Collection.php",
    "groupTitle": "Node_Collection",
    "sampleRequest": [
      {
        "url": " /api/v1/collection/share?id=:id"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "head",
    "url": "/api/v1/collection/children?id=:id",
    "title": "children exists?",
    "version": "1.0.6",
    "name": "head",
    "group": "Node_Collection",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Check if collection has any children</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XHEAD \"https://SERVER/api/v1/collection/children?id=544627ed3c58891f058b4686\"\ncurl -XHEAD \"https://SERVER/api/v1/collection/544627ed3c58891f058b4686/children\"\ncurl -XHEAD \"https://SERVER/api/v1/collection/children?p=/absolute/path/to/my/collection\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response (Children exists):",
          "content": "HTTP/1.1 204 Not Content",
          "type": "json"
        }
      ]
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (No children exists):",
          "content": "HTTP/1.1 404 Not Found",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ],
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      }
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Collection.php",
    "groupTitle": "Node_Collection",
    "sampleRequest": [
      {
        "url": " /api/v1/collection/children?id=:id"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    }
  },
  {
    "type": "post",
    "url": "/api/v1/collection?id=:id",
    "title": "Create collection",
    "version": "1.0.6",
    "name": "post",
    "group": "Node_Collection",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Create a new collection. You can create a new collection combining a parent collection (id) and a name (name) or set an absolute path (p) to the new collection. Additionally it is possible to overwrite server generated timestamps like created or changed (attributes). Via the more advanced option filter (attributes.filter) you can create a special collection which can contain any nodes based on the given filter. For example a filter could be {mime: application/pdf}, therefore the collection would contain all files with mimetype application/pdf accessible by you. (Attention this can result in a slow server response since you could create a filter where no indexes exists, therefore the database engine needs to search the entire database)</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/collection?id=544627ef3c58891f058b468f&name=MyNewFolder&pretty\"\ncurl -XGET \"https://SERVER/api/v1/collection/544627ef3c58891f058b468f?name=MyNewFolder&pretty\"\ncurl -XGET \"https://SERVER/api/v1/collection/?p=/absolute/path/to/my/collection&name=MyNewFolder&pretty&conflict=2\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given. If a path is given, no name must be set, the path must contain the name of the new collection.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "name",
            "description": "<p>A collection name must be set in conjuction with id, don't need to set with a path</p>"
          },
          {
            "group": "GET Parameter",
            "type": "object",
            "optional": false,
            "field": "attributes",
            "description": "<p>Overwrite some attributes which are usually generated on the server</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "attributes.created",
            "description": "<p>Set specific created timestamp (UNIX timestamp format)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "attributes.changed",
            "description": "<p>Set specific changed timestamp (UNIX timestamp format)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "attributes.destroy",
            "description": "<p>Set specific self-destroy timestamp (UNIX timestamp format)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "array",
            "optional": false,
            "field": "attributes.filter",
            "description": "<p>Set specific set of children instead just parent=this</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "conflict",
            "defaultValue": "0",
            "description": "<p>Decides how to handle a conflict if a node with the same name already exists at the destination. Possible values are:</br></p> <ul> <li>0 No action</br></li> <li>1 Automatically rename the node</br></li> <li>2 Overwrite the destination (merge)</br></li> </ul>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "201 Created": [
          {
            "group": "201 Created",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "201 Created",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Node ID</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 201 Created\n{\n     \"status\":201,\n     \"data\": \"544627ed3c58891f058b4682\"\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Collection.php",
    "groupTitle": "Node_Collection",
    "sampleRequest": [
      {
        "url": " /api/v1/collection?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/file/history?id=:id",
    "title": "Get history",
    "version": "1.0.6",
    "name": "getHistory",
    "group": "Node_File",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get a full change history of a file</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/file/history?id=544627ed3c58891f058b4686&pretty\"\ncurl -XGET \"https://SERVER/api/v1/file/544627ed3c58891f058b4686/history?pretty\"\ncurl -XGET \"https://SERVER/api/v1/file/history?p=/absolute/path/to/my/file&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>History</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.version",
            "description": "<p>Version</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.changed",
            "description": "<p>Changed timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.sec",
            "description": "<p>Changed timestamp in Unix time</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.usec",
            "description": "<p>Additional microseconds to changed Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.user",
            "description": "<p>User which changed the version</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.type",
            "description": "<p>Change type, there are five different change types including:</br> 0 - Initially added</br> 1 - Content modified</br> 2 - Version rollback</br> 3 - Deleted</br> 4 - Undeleted</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.file",
            "description": "<p>Reference to the content</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.file.id",
            "description": "<p>Content reference ID</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.size",
            "description": "<p>Content size in bytes</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.mime",
            "description": "<p>Content mime type</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\": 200,\n     \"data\": [\n         {\n             \"version\": 1,\n             \"changed\": {\n                 \"sec\": 1413883885,\n                 \"usec\": 876000\n             },\n             \"user\": \"peter.meier\",\n             \"type\": 0,\n             \"file\": {\n                 \"$id\": \"544627ed3c58891f058b4688\"\n             },\n             \"size\": 178,\n             \"mime\": \"text\\/plain\"\n         }\n     ]\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/File.php",
    "groupTitle": "Node_File",
    "sampleRequest": [
      {
        "url": " /api/v1/file/history?id=:id"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/file/preview?id=:id",
    "title": "Get Preview",
    "version": "1.0.6",
    "name": "getPreview",
    "group": "Node_File",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get a preview of the files content. The body either contains an encoded string or a jpeg binary</p>",
    "examples": [
      {
        "title": "(cURL) exmaple:",
        "content": "curl -XGET \"https://SERVER/api/v1/file/preview?id=544627ed3c58891f058b4686 > preview.jpg\"\ncurl -XGET \"https://SERVER/api/v1/file/544627ed3c58891f058b4686/preview > preview.jpg\"\ncurl -XGET \"https://SERVER/api/v1/file/preview?p=/absolute/path/to/my/file > preview.jpg\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "encode",
            "defaultValue": "false",
            "description": "<p>Set to base64 to return a jpeg encoded preview as base64, else return it as jpeg binary</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK",
          "type": "string"
        },
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK",
          "type": "binary"
        }
      ]
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (thumbnail not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"no preview exists\"\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ],
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      }
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/File.php",
    "groupTitle": "Node_File",
    "sampleRequest": [
      {
        "url": " /api/v1/file/preview?id=:id"
      }
    ]
  },
  {
    "type": "post",
    "url": "/api/v1/file/restore?id=:id",
    "title": "Rollback version",
    "version": "1.0.6",
    "name": "postRestore",
    "group": "Node_File",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Rollback to a recent version from history. Use the version number from history.</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/file/restore?id=544627ed3c58891f058b4686&pretty&vesion=11\"\ncurl -XPOST \"https://SERVER/api/v1/file/544627ed3c58891f058b4686/restore?pretty&version=1\"\ncurl -XPOST \"https://SERVER/api/v1/file/restore?p=/absolute/path/to/my/file&pretty&version=3\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "version",
            "description": "<p>The version from history to rollback to</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/File.php",
    "groupTitle": "Node_File",
    "sampleRequest": [
      {
        "url": " /api/v1/file/restore?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "put",
    "url": "/api/v1/file",
    "title": "Upload file",
    "version": "1.0.6",
    "name": "put",
    "group": "Node_File",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Upload an entire file in one-shot. Attention, there is file size limit, if you have possible big files use the method PUT chunk!</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "#Update content of file 544627ed3c58891f058b4686\ncurl -XPUT \"https://SERVER/api/v1/file?id=544627ed3c58891f058b4686\" --data-binary myfile.txt\ncurl -XPUT \"https://SERVER/api/v1/file/544627ed3c58891f058b4686\" --data-binary myfile.txt\n\n#Upload new file under collection 544627ed3c58891f058b3333\ncurl -XPUT \"https://SERVER/api/v1/file?collection=544627ed3c58891f058b3333&name=myfile.txt\" --data-binary myfile.txt",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "id",
            "description": "<p>Either id, p (path) of a file node or a parent collection id must be given</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "p",
            "description": "<p>Either id, p (path) of a file node or a parent collection id must be given</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "collection",
            "description": "<p>Either id, p (path) of a file node or a parent collection id must be given (If none of them are given, the file will be placed to the root)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "name",
            "description": "<p>Needs to be set if the chunk belongs to a new file or to identify an existing child file if a collection id was set</p>"
          },
          {
            "group": "GET Parameter",
            "type": "object",
            "optional": false,
            "field": "attributes",
            "description": "<p>Overwrite some attributes which are usually generated on the server</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "attributes.created",
            "description": "<p>Set specific created timestamp (UNIX timestamp format)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "attributes.changed",
            "description": "<p>Set specific changed timestamp (UNIX timestamp format)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "conflict",
            "defaultValue": "0",
            "description": "<p>Decides how to handle a conflict if a node with the same name already exists at the destination. Possible values are:</br></p> <ul> <li>0 No action</br></li> <li>1 Automatically rename the node</br></li> <li>2 Overwrite the destination (merge)</br></li> </ul>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data",
            "description": "<p>Increased version number if an existing file was updated. It will return the old version if the submited file content was equal to the existing one.</p>"
          }
        ],
        "201 Created": [
          {
            "group": "201 Created",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "201 Created",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Node ID</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response (New file created):",
          "content": "HTTP/1.1 201 Created\n{\n     \"status\": 201,\n     \"data\": \"78297329329389e332234342\"\n}",
          "type": "json"
        },
        {
          "title": "Success-Response (File updated):",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\": 200,\n     \"data\": 2\n}",
          "type": "json"
        }
      ]
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (quota full):",
          "content": "HTTP/1.1 507 Insufficient Storage\n{\n     \"status\": 507\n     \"data\": {\n         \"error\": \"Balloon\\Exception\\InsufficientStorage\",\n         \"message\": \"user quota is full\",\n         \"code\": 65\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Size limit exceeded):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"file size exceeded limit\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ],
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      }
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/File.php",
    "groupTitle": "Node_File",
    "sampleRequest": [
      {
        "url": " /api/v1/file"
      }
    ]
  },
  {
    "type": "put",
    "url": "/api/v1/file/chunk",
    "title": "Upload file chunk",
    "version": "1.0.6",
    "name": "putChunk",
    "group": "Node_File",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Upload a file chunk. Use this method if you have possible big files! You have to manually splitt the binary data into multiple chunks and upload them successively using this method. Once uploading the last chunk, the server will automatically create or update the file node. You may set the parent collection, name and or custom attributes only with the last request to save traffic.</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "# Upload a new file myfile.jpg into the collection 544627ed3c58891f058b4686.\n1. First splitt the file into multiple 8M (For example, you could also use a smaller or bigger size) chunks\n2. Create a unique name for the chunkgroup (Could also be the filename), best thing is to create a UUIDv4\n3. Upload each chunk successively (follow the binary order of your file!) using the chunk PUT method\n  (The server identifies each chunk with the index parameter, beginning with #1).\n4. If chunk number 3 will be reached, the server automatically place all chunks to the new file node\n\ncurl -XPUT \"https://SERVER/api/v1/file/chunk?collection=544627ed3c58891f058b4686&name=myfile.jpg&index=1&chunks=3&chunkgroup=myuniquechunkgroup&size=12342442&pretty\" --data-binary @chunk1.bin\ncurl -XPUT \"https://SERVER/api/v1/file/chunk?collection=544627ed3c58891f058b4686&name=myfile.jpg&index=2&chunks=3&chunkgroup=myuniquechunkgroup&size=12342442&pretty\" --data-binary @chunk2.bin\ncurl -XPUT \"https://SERVER/api/v1/file/chunk?collection=544627ed3c58891f058b4686&name=myfile.jpg&index=3&chunks=3&chunkgroup=myuniquechunkgroup&size=12342442&pretty\" --data-binary @chunk3.bin",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "id",
            "description": "<p>Either id, p (path) of a file node or a parent collection id must be given</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "p",
            "description": "<p>Either id, p (path) of a file node or a parent collection id must be given</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "collection",
            "description": "<p>Either id, p (path) of a file node or a parent collection id must be given (If none of them are given, the file will be placed to the root)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "name",
            "description": "<p>Needs to be set if the chunk belongs to a new file</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "index",
            "description": "<p>Chunk ID (consider chunk order!)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "chunks",
            "description": "<p>Total number of chunks</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "chunkgroup",
            "description": "<p>A unique name which identifes a group of chunks (One file)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "size",
            "description": "<p>The total file size in bytes</p>"
          },
          {
            "group": "GET Parameter",
            "type": "object",
            "optional": true,
            "field": "attributes",
            "description": "<p>Overwrite some attributes which are usually generated on the server</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "attributes.created",
            "description": "<p>Set specific created timestamp (UNIX timestamp format)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "attributes.changed",
            "description": "<p>Set specific changed timestamp (UNIX timestamp format)</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data",
            "description": "<p>Increased version number if the last chunk was uploaded and existing node was updated. It will return the old version if the submited file content was equal to the existing one.</p>"
          }
        ],
        "201 Created": [
          {
            "group": "201 Created",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "201 Created",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Node ID if the last chunk was uploaded and a new node was added</p>"
          }
        ],
        "206 Partial Content": [
          {
            "group": "206 Partial Content",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "206 Partial Content",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Chunk ID if it was not the last chunk</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response (Not the last chunk yet):",
          "content": "HTTP/1.1 206 Partial Content\n{\n     \"status\": 206,\n     \"data\": \"1\"\n}",
          "type": "json"
        },
        {
          "title": "Success-Response (New file created, Last chunk):",
          "content": "HTTP/1.1 201 Created\n{\n     \"status\": 201,\n     \"data\": \"78297329329389e332234342\"\n}",
          "type": "json"
        },
        {
          "title": "Success-Response (File updated, Last chunk):",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\": 200,\n     \"data\": 2\n}",
          "type": "json"
        }
      ]
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (quota full):",
          "content": "HTTP/1.1 507 Insufficient Storage\n{\n     \"status\": 507\n     \"data\": {\n         \"error\": \"Balloon\\Exception\\InsufficientStorage\",\n         \"message\": \"user quota is full\",\n         \"code\": 66\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Size limit exceeded):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"file size exceeded limit\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Chunks lost):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"chunks lost, reupload all chunks\",\n         \"code\": 275\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Chunks invalid size):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"merged chunks temp file size is not as expected\",\n         \"code\": 276\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ],
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      }
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/File.php",
    "groupTitle": "Node_File",
    "sampleRequest": [
      {
        "url": " /api/v1/file/chunk"
      }
    ]
  },
  {
    "type": "delete",
    "url": "/api/v1/node?id=:id",
    "title": "Delete node",
    "version": "1.0.6",
    "name": "delete",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Delete node</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "boolean",
            "optional": true,
            "field": "force",
            "defaultValue": "false",
            "description": "<p>Force flag need to be set to delete a node from trash (node must have the deleted flag)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "boolean",
            "optional": true,
            "field": "ignore_flag",
            "defaultValue": "false",
            "description": "<p>If both ignore_flag and force_flag were set, the node will be deleted completely</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "at",
            "description": "<p>Has to be a valid unix timestamp if so the node will destroy itself at this specified time instead immediatly</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "id",
            "description": "<p>Either a single id as string or multiple as an array or a single p (path) as string or multiple paths as array must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "p",
            "description": "<p>Either a single id as string or multiple as an array or a single p (path) as string or multiple paths as array must be given.</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XDELETE \"https://SERVER/api/v1/node?id=544627ed3c58891f058b4686\"\ncurl -XDELETE \"https://SERVER/api/v1/node/544627ed3c58891f058b4686?force=1&ignore_flag=1\"\ncurl -XDELETE \"https://SERVER/api/v1/node?p=/absolute/path/to/my/node\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>General error messages of type  Balloon\\Exception do not usually have an error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Multi node error):",
          "content": "HTTP/1.1 400 Bad Request\n{\n    \"status\": 400,\n    \"data\": [\n        {\n             id: \"51354d073c58891f058b4580\",\n             name: \"file.zip\",\n             error: \"Balloon\\\\Exception\\\\Conflict\",\n             message: \"node already exists\",\n             code: 30\n        }\n    ]\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "delete",
    "url": "/api/v1/node/share-link?id=:id",
    "title": "Delete sharing link",
    "version": "1.0.6",
    "name": "deleteShareLink",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Delete an existing sharing link</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XDELETE \"https://SERVER/api/v1/node/share-link?id=544627ed3c58891f058b4686?pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/share-link?id=:id"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/node/last-cursor",
    "title": "Get last Cursor",
    "version": "1.0.6",
    "name": "geLastCursor",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Use this method to request the latest cursor if you only need to now if there are changes on the server. This method will not return any other data than the newest cursor. To request a feed with all deltas request /delta.</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/node/last-cursor?pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>Newest cursor</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\": 200,\n     \"data\": \"aW5pdGlhbHwxMDB8NTc1YTlhMGIzYzU4ODkwNTE0OGI0NTZifDU3NWE5YTBiM2M1ODg5MDUxNDhiNDU2Yw==\"\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/last-cursor"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/node?id=:id",
    "title": "Download stream",
    "version": "1.0.6",
    "name": "get",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Download node contents. Collections (Folder) are converted into a zip file in realtime.</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "offset",
            "defaultValue": "0",
            "description": "<p>Get content from a specific offset in bytes</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "length",
            "defaultValue": "0",
            "description": "<p>Get content with until length in bytes reached</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "encode",
            "description": "<p>Can be set to base64 to encode content as base64.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "boolean",
            "optional": true,
            "field": "download",
            "defaultValue": "false",
            "description": "<p>Force download file (Content-Disposition: attachment HTTP header)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/node?id=544627ed3c58891f058b4686\" > myfile.txt\ncurl -XGET \"https://SERVER/api/v1/node/544627ed3c58891f058b4686\" > myfile.txt\ncurl -XGET \"https://SERVER/api/v1/node?p=/absolute/path/to/my/collection\" > folder.zip",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response (encode=base64):",
          "content": "HTTP/1.1 200 OK",
          "type": "string"
        },
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK",
          "type": "binary"
        }
      ]
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (Invalid offset):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"invalid offset requested\",\n         \"code\": 277\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ],
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      }
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node?id=:id"
      }
    ]
  },
  {
    "type": "get",
    "url": "/api/v1/node/attributes?id=:id",
    "title": "Get attributes",
    "version": "1.0.6",
    "name": "getAttributes",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get node attribute</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": true,
            "field": "attributes",
            "description": "<p>Filter attributes, per default only a bunch of attributes would be returned, if you need other attributes you have to request them (for example &quot;path&quot;)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/node/attributes?id=544627ed3c58891f058b4686&pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/attributes?id=544627ed3c58891f058b4686&attributes[0]=name&attributes[1]=deleted&pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/attributes?pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/attributes?p=/absolute/path/to/my/node&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": {\n         \"id\": \"544627ed3c58891f058b4686\",\n         \"name\": \"api.php\",\n         \"hash\": \"a77f23ed800fd7a600a8c2cfe8cc370b\",\n         \"meta\": {\n             \"license\": \"GPLv3\"\n         },\n         \"size\": 178,\n         \"mime\": \"text\\/plain\",\n         \"sharelink\": true,\n         \"version\": 1,\n         \"deleted\": false,\n         \"changed\": {\n             \"sec\": 1413883885,\n             \"usec\": 869000\n         },\n         \"created\": {\n             \"sec\": 1413883885,\n             \"usec\": 869000\n         },\n         \"share\": false,\n         \"thumbnail\": \"544628243c5889b86d8b4568\",\n         \"directory\": false\n     }\n}",
          "type": "json"
        }
      ],
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>Attributes</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Unique node id</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.name",
            "description": "<p>Name</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.hash",
            "description": "<p>MD5 content checksum (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.meta",
            "description": "<p>Extended meta attributes</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.description",
            "description": "<p>UTF-8 Text Description</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.color",
            "description": "<p>Color Tag (HEX) (Like: #000000)</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.author",
            "description": "<p>Author</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.mail",
            "description": "<p>Mail contact address</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.license",
            "description": "<p>License</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.copyright",
            "description": "<p>Copyright string</p>"
          },
          {
            "group": "200 OK",
            "type": "string[]",
            "optional": false,
            "field": "data.meta.tags",
            "description": "<p>Search Tags</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.size",
            "description": "<p>Size in bytes (Only file node), number of children if collection</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.mime",
            "description": "<p>Mime type</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.sharelink",
            "description": "<p>Is node shared?</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.version",
            "description": "<p>File version (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "mixed",
            "optional": false,
            "field": "data.deleted",
            "description": "<p>Is boolean false if not deleted, if deleted it contains a deleted timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.changed",
            "description": "<p>Changed timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.created",
            "description": "<p>Created timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.share",
            "description": "<p>Node is shared</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.directory",
            "description": "<p>Is node a collection or a file?</p>"
          }
        ],
        "200 OK - additional attributes": [
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.thumbnail",
            "description": "<p>Id of preview (file node only)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.access",
            "description": "<p>Access if node is shared, one of r/rw/w</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.shareowner",
            "description": "<p>Username of the share owner</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.parent",
            "description": "<p>ID of the parent node</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.path",
            "description": "<p>Absolute node path</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.filtered",
            "description": "<p>Node is filtered (usually only a collection)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.readonly",
            "description": "<p>Node is readonly</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "object[]",
            "optional": false,
            "field": "data.history",
            "description": "<p>Get file history (file node only)</p>"
          }
        ]
      }
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/attributes?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/node/delta",
    "title": "Get delta",
    "version": "1.0.6",
    "name": "getDelta",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Use this method to request a delta feed with all changes on the server (or) a snapshot of the server state. since the state of the submited cursor. If no cursor was submited the server will create one which can than be used to request any further deltas. If has_more is TRUE you need to request /delta immediatly again to receive the next bunch of deltas. If has_more is FALSE you should wait at least 120s seconds before any further requests to the api endpoint. You can also specify additional node attributes with the $attributes paramter or request the delta feed only for a specific node (see Get Attributes for that). If reset is TRUE you have to clean your local state because you will receive a snapshot of the server state, it is the same as calling the /delta endpoint without a cursor. reset could be TRUE if there was an account maintenance or a simialar case. You can request a different limit as well but be aware that the number of nodes could be slighty different from your requested limit. If requested with parameter id or p the delta gets generated recursively from the node given.</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "limit",
            "defaultValue": "250",
            "description": "<p>Limit the number of delta entries, if too low you have to call this endpoint more often since has_more would be true more often</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": true,
            "field": "attributes",
            "description": "<p>Filter attributes, per default not all attributes would be returned</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "cursor",
            "defaultValue": "null",
            "description": "<p>Set a cursor to rquest next nodes within delta processing</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/node/delta?pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>Delta feed</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.reset",
            "description": "<p>If true the local state needs to be reseted, is alway TRUE during the first request to /delta without a cursor or in special cases like server or account maintenance</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.cursor",
            "description": "<p>The cursor needs to be stored and reused to request further deltas</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.has_more",
            "description": "<p>If has_more is TRUE /delta can be requested immediatly after the last request to receive further delta. If it is FALSE we should wait at least 120 seconds before any further delta requests to the api endpoint</p>"
          },
          {
            "group": "200 OK",
            "type": "object[]",
            "optional": false,
            "field": "data.nodes",
            "description": "<p>Node list to process</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.nodes.id",
            "description": "<p>Node ID</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.nodes.deleted",
            "description": "<p>Is node deleted?</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.nodes.changed",
            "description": "<p>Changed timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.nodes.changed.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.nodes.changed.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.nodes.created",
            "description": "<p>Created timestamp (If data.nodes[].deleted is TRUE, created will be NULL)</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.nodes.created.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.nodes.created.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.nodes.path",
            "description": "<p>The full absolute path to the node</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.nodes.directory",
            "description": "<p>Is true if node is a directory</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\": 200,\n     \"data\": {\n         \"reset\": false,\n         \"cursor\": \"aW5pdGlhbHwxMDB8NTc1YTlhMGIzYzU4ODkwNTE0OGI0NTZifDU3NWE5YTBiM2M1ODg5MDUxNDhiNDU2Yw==\",\n         \"has_more\": false,\n         \"nodes\": [\n            {\n                 \"id\": \"581afa783c5889ad7c8b4572\",\n                 \"deleted\": true,\n                 \"created\": null,\n                 \"changed\": {\n                     \"sec\": 1478163064,\n                     \"usec\": 317000\n                 },\n                 \"path\": \"\\/AAA\\/AL\",\n                 \"directory\": true\n             },\n             {\n                 \"id\": \"581afa783c5889ad7c8b3dcf\",\n                 \"deleted\": false,\n                 \"created\": {\n                     \"sec\": 1478163048,\n                     \"usec\": 101000\n                 },\n                 \"changed\": {\n                     \"sec\": 1478163048,\n                     \"usec\": 101000\n                 },\n                 \"path\": \"\\/AL\",\n                 \"directory\": true\n             }\n         ]\n     }\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/delta"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/node/event-log?id=:id",
    "title": "Event log",
    "version": "1.0.6",
    "name": "getEventLog",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get detailed event log Request all modifications which are made by the user himself or share members. Possible operations are the follwing:</p> <ul> <li>deleteCollectionReference</li> <li>deleteCollectionShare</li> <li>deleteCollection</li> <li>addCollection</li> <li>addFile</li> <li>addCollectionShare</li> <li>addCollectionReference</li> <li>undeleteFile</li> <li>undeleteCollectionReference</li> <li>undeleteCollectionShare</li> <li>restoreFile</li> <li>renameFile</li> <li>renameCollection</li> <li>renameCollectionShare</li> <li>renameCollectionRFeference</li> <li>copyFile</li> <li>copyCollection</li> <li>copyCollectionShare</li> <li>copyCollectionRFeference</li> <li>moveFile</li> <li>moveCollection</li> <li>moveCollectionReference</li> <li>moveCollectionShare</li> </ul>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/node/event-log?pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/event-log?id=544627ed3c58891f058b4686&pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/event-log?pretty&limit=10\"\ncurl -XGET \"https://SERVER/api/v1/node/event-log?p=/absolute/path/to/my/node&pretty\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "limit",
            "defaultValue": "100",
            "description": "<p>Sets limit of events to be returned</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "skip",
            "defaultValue": "0",
            "description": "<p>How many events are skiped (useful for paging)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Events</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.event",
            "description": "<p>Event ID</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.timestamp",
            "description": "<p>event timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.timestamp.sec",
            "description": "<p>Event timestamp timestamp in Unix time</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.timestamp.usec",
            "description": "<p>Additional microseconds to changed Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.operation",
            "description": "<p>event operation (like addCollection, deleteFile, ...)</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.parent",
            "description": "<p>ID of the parent node at the time of the event</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.previous",
            "description": "<p>Previous state of actual data which has been modified during an event, can contain either version, name or parent</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.previous.version",
            "description": "<p>Version at the time before the event</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.previous.name",
            "description": "<p>Name at the time before the event</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.previous.parent",
            "description": "<p>Parent node at the time before the event</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.share",
            "description": "<p>If of the shared folder at the time of the event</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.name",
            "description": "<p>Name of the node at the time of the event</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.node",
            "description": "<p>Current data of the node (Not from the time of the event!)</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.node.deleted",
            "description": "<p>True if the node is deleted, false otherwise</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.node.id",
            "description": "<p>Actual ID of the node</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.node.name",
            "description": "<p>Current name of the node</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.user",
            "description": "<p>Data which contains information about the user who executed an event</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.user.id",
            "description": "<p>Actual user ID</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.user.username",
            "description": "<p>Current username of executed event</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\": 200,\n     \"data\": [\n         {\n             \"event\": \"57628e523c5889026f8b4570\",\n             \"timestamp\": {\n                 \"sec\": 1466076753,\n                 \"usec\": 988000\n             },\n             \"operation\": \"restoreFile\",\n             \"name\": \"file.txt\",\n             \"previous\": {\n                 \"version\": 16\n             },\n             \"node\": {\n                 \"id\": \"558c0b273c588963078b457a\",\n                 \"name\": \"3dddsceheckfile.txt\",\n                 \"deleted\": false\n             },\n             \"parent\": null,\n             \"user\": {\n                 \"id\": \"54354cb63c58891f058b457f\",\n                 \"username\": \"gradmin.bzu\"\n             },\n             \"share\": null\n         }\n     ]\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/event-log?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/node/parent?id=:id",
    "title": "Get parent node",
    "version": "1.0.6",
    "name": "getParent",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get system attributes of the parent node</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/node/parent?id=544627ed3c58891f058b4686&pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/parent?id=544627ed3c58891f058b4686&attributes[0]=name&attributes[1]=deleted?pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/parent?pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/parent?p=/absolute/path/to/my/node&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": {\n         \"id\": \"544627ed3c58891f058b46cc\",\n         \"name\": \"exampledir\",\n         \"meta\": {},\n         \"size\": 3,\n         \"mime\": \"inode\\/directory\",\n         \"deleted\": false,\n         \"changed\": {\n             \"sec\": 1413883885,\n             \"usec\": 869000\n         },\n         \"created\": {\n             \"sec\": 1413883885,\n             \"usec\": 869000\n         },\n         \"share\": false,\n         \"directory\": true\n     }\n}",
          "type": "json"
        }
      ],
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>Attributes</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Unique node id</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.name",
            "description": "<p>Name</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.hash",
            "description": "<p>MD5 content checksum (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.meta",
            "description": "<p>Extended meta attributes</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.description",
            "description": "<p>UTF-8 Text Description</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.color",
            "description": "<p>Color Tag (HEX) (Like: #000000)</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.author",
            "description": "<p>Author</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.mail",
            "description": "<p>Mail contact address</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.license",
            "description": "<p>License</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.copyright",
            "description": "<p>Copyright string</p>"
          },
          {
            "group": "200 OK",
            "type": "string[]",
            "optional": false,
            "field": "data.meta.tags",
            "description": "<p>Search Tags</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.size",
            "description": "<p>Size in bytes (Only file node), number of children if collection</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.mime",
            "description": "<p>Mime type</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.sharelink",
            "description": "<p>Is node shared?</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.version",
            "description": "<p>File version (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "mixed",
            "optional": false,
            "field": "data.deleted",
            "description": "<p>Is boolean false if not deleted, if deleted it contains a deleted timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.changed",
            "description": "<p>Changed timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.created",
            "description": "<p>Created timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.share",
            "description": "<p>Node is shared</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.directory",
            "description": "<p>Is node a collection or a file?</p>"
          }
        ],
        "200 OK - additional attributes": [
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.thumbnail",
            "description": "<p>Id of preview (file node only)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.access",
            "description": "<p>Access if node is shared, one of r/rw/w</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.shareowner",
            "description": "<p>Username of the share owner</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.parent",
            "description": "<p>ID of the parent node</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.path",
            "description": "<p>Absolute node path</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.filtered",
            "description": "<p>Node is filtered (usually only a collection)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.readonly",
            "description": "<p>Node is readonly</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "object[]",
            "optional": false,
            "field": "data.history",
            "description": "<p>Get file history (file node only)</p>"
          }
        ]
      }
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/parent?id=:id"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": true,
            "field": "attributes",
            "description": "<p>Filter attributes, per default not all attributes would be returned</p>"
          }
        ]
      }
    },
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/node/parents?id=:id",
    "title": "Get parent nodes",
    "version": "1.0.6",
    "name": "getParents",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get system attributes of all parent nodes. The hirarchy of all parent nodes is ordered in a single level array beginning with the collection on the highest level.</p>",
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Nodes</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Unique node id</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.name",
            "description": "<p>Name</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.hash",
            "description": "<p>MD5 content checksum (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.meta",
            "description": "<p>Extended meta attributes</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.description",
            "description": "<p>UTF-8 Text Description</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.color",
            "description": "<p>Color Tag (HEX) (Like: #000000)</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.author",
            "description": "<p>Author</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.mail",
            "description": "<p>Mail contact address</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.license",
            "description": "<p>License</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.copyright",
            "description": "<p>Copyright string</p>"
          },
          {
            "group": "200 OK",
            "type": "string[]",
            "optional": false,
            "field": "data.meta.tags",
            "description": "<p>Search Tags</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.size",
            "description": "<p>Size in bytes (Only file node), number of children if collection</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.mime",
            "description": "<p>Mime type</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.sharelink",
            "description": "<p>Is node shared?</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.version",
            "description": "<p>File version (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "mixed",
            "optional": false,
            "field": "data.deleted",
            "description": "<p>Is boolean false if not deleted, if deleted it contains a deleted timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.changed",
            "description": "<p>Changed timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.created",
            "description": "<p>Created timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.share",
            "description": "<p>Node is shared</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.directory",
            "description": "<p>Is node a collection or a file?</p>"
          }
        ],
        "200 OK - additional attributes": [
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.thumbnail",
            "description": "<p>Id of preview (file node only)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.access",
            "description": "<p>Access if node is shared, one of r/rw/w</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.shareowner",
            "description": "<p>Username of the share owner</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.parent",
            "description": "<p>ID of the parent node</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.path",
            "description": "<p>Absolute node path</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.filtered",
            "description": "<p>Node is filtered (usually only a collection)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.readonly",
            "description": "<p>Node is readonly</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "object[]",
            "optional": false,
            "field": "data.history",
            "description": "<p>Get file history (file node only)</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": [\n                {\n             \"id\": \"544627ed3c58891f058bbbaa\",\n             \"name\": \"rootdir\",\n             \"meta\": {},\n             \"size\": 1,\n             \"mime\": \"inode\\/directory\",\n             \"deleted\": false,\n             \"changed\": {\n                 \"sec\": 1413883880,\n                 \"usec\": 869001\n             },\n             },\n             \"created\": {\n                 \"sec\": 1413883880,\n                 \"usec\": 869001\n             },\n             \"share\": false,\n             \"directory\": true\n         },\n         {\n             \"id\": \"544627ed3c58891f058b46cc\",\n             \"name\": \"parentdir\",\n             \"meta\": {},\n             \"size\": 3,\n             \"mime\": \"inode\\/directory\",\n             \"deleted\": false,\n             \"changed\": {\n                 \"sec\": 1413883885,\n                 \"usec\": 869000\n             },\n             \"created\": {\n                 \"sec\": 1413883885,\n                 \"usec\": 869000\n             },\n             \"share\": false,\n             \"directory\": true\n         }\n     ]\n}",
          "type": "json"
        }
      ]
    },
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "boolean",
            "optional": true,
            "field": "self",
            "defaultValue": "true",
            "description": "<p>Include requested collection itself at the end of the list (Will be ignored if the requested node is a file)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": true,
            "field": "attributes",
            "description": "<p>Filter attributes, per default not all attributes would be returned</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/node/parents?id=544627ed3c58891f058b4686&pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/parents?id=544627ed3c58891f058b4686&attributes[0]=name&attributes[1]=deleted&pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/parents?pretty&self=1\"\ncurl -XGET \"https://SERVER/api/v1/node/parents?p=/absolute/path/to/my/node&self=1\"",
        "type": "json"
      }
    ],
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/parents?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/node/query",
    "title": "Custom query",
    "version": "1.0.6",
    "name": "getQuery",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>A custom query is similar requet to children. You do not have to provide any parent node (id or p) but you have to provide a filter therefore you can collect any nodes which do match the provided filter. It is a form of a search (search) but does not use the search engine like GET /node/search does. You can also create a persistent query collection, just look at POST /collection, there you can attach a filter option to the attributes paramater which would be the same as a custom query but just persistent. Since query parameters can only be strings and you perhaps would like to filter other data types, you have to send json as parameter to the server.</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET https://SERVER/api/v1/node/query?{%22filter%22:{%22shared%22:true,%22reference%22:{%22$exists%22:0}}}",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": true,
            "field": "attributes",
            "description": "<p>Filter node attributes</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": true,
            "field": "filter",
            "description": "<p>Filter nodes</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "deleted",
            "defaultValue": "0",
            "description": "<p>Wherever include deleted nodes or not, possible values:</br></p> <ul> <li>0 Exclude deleted</br></li> <li>1 Only deleted</br></li> <li>2 Include deleted</br></li> </ul>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Children</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Unique node id</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.name",
            "description": "<p>Name</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.hash",
            "description": "<p>MD5 content checksum (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.meta",
            "description": "<p>Extended meta attributes</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.description",
            "description": "<p>UTF-8 Text Description</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.color",
            "description": "<p>Color Tag (HEX) (Like: #000000)</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.author",
            "description": "<p>Author</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.mail",
            "description": "<p>Mail contact address</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.license",
            "description": "<p>License</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.copyright",
            "description": "<p>Copyright string</p>"
          },
          {
            "group": "200 OK",
            "type": "string[]",
            "optional": false,
            "field": "data.meta.tags",
            "description": "<p>Search Tags</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.size",
            "description": "<p>Size in bytes (Only file node), number of children if collection</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.mime",
            "description": "<p>Mime type</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.sharelink",
            "description": "<p>Is node shared?</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.version",
            "description": "<p>File version (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "mixed",
            "optional": false,
            "field": "data.deleted",
            "description": "<p>Is boolean false if not deleted, if deleted it contains a deleted timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.changed",
            "description": "<p>Changed timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.created",
            "description": "<p>Created timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.share",
            "description": "<p>Node is shared</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.directory",
            "description": "<p>Is node a collection or a file?</p>"
          }
        ],
        "200 OK - additional attributes": [
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.thumbnail",
            "description": "<p>Id of preview (file node only)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.access",
            "description": "<p>Access if node is shared, one of r/rw/w</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.shareowner",
            "description": "<p>Username of the share owner</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.parent",
            "description": "<p>ID of the parent node</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.path",
            "description": "<p>Absolute node path</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.filtered",
            "description": "<p>Node is filtered (usually only a collection)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.readonly",
            "description": "<p>Node is readonly</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "object[]",
            "optional": false,
            "field": "data.history",
            "description": "<p>Get file history (file node only)</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\":200,\n     \"data\": [{..}, {...}] //Shorted\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/query"
      }
    ]
  },
  {
    "type": "get",
    "url": "/api/v1/node/search",
    "title": "Search",
    "version": "1.0.6",
    "name": "getSearch",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Extended search query, using the integrated search engine (elasticsearch).</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "#Fulltext search and search for a name\ncurl -XGET -H 'Content-Type: application/json' \"https://SERVER/api/v1/node/search?pretty\" -d '{\n          \"body\": {\n              \"query\": {\n                  \"bool\": {\n                      \"should\": [\n                          {\n                              \"match\": {\n                                  \"content\": \"house\"\n                              }\n                          },\n                          {\n                              \"match\": {\n                                  \"name\": \"file.txt\"\n                              }\n                          }\n                      ]\n                  }\n              }\n          }\n      }'",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "object",
            "optional": false,
            "field": "query",
            "description": "<p>Elasticsearch query object</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": true,
            "field": "attributes",
            "description": "<p>Filter node attributes</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "deleted",
            "defaultValue": "0",
            "description": "<p>Wherever include deleted nodes or not, possible values:</br></p> <ul> <li>0 Exclude deleted</br></li> <li>1 Only deleted</br></li> <li>2 Include deleted</br></li> </ul>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Node list (matched nodes)</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Unique node id</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.name",
            "description": "<p>Name</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.hash",
            "description": "<p>MD5 content checksum (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.meta",
            "description": "<p>Extended meta attributes</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.description",
            "description": "<p>UTF-8 Text Description</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.color",
            "description": "<p>Color Tag (HEX) (Like: #000000)</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.author",
            "description": "<p>Author</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.mail",
            "description": "<p>Mail contact address</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.license",
            "description": "<p>License</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.copyright",
            "description": "<p>Copyright string</p>"
          },
          {
            "group": "200 OK",
            "type": "string[]",
            "optional": false,
            "field": "data.meta.tags",
            "description": "<p>Search Tags</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.size",
            "description": "<p>Size in bytes (Only file node), number of children if collection</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.mime",
            "description": "<p>Mime type</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.sharelink",
            "description": "<p>Is node shared?</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.version",
            "description": "<p>File version (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "mixed",
            "optional": false,
            "field": "data.deleted",
            "description": "<p>Is boolean false if not deleted, if deleted it contains a deleted timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.changed",
            "description": "<p>Changed timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.created",
            "description": "<p>Created timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.share",
            "description": "<p>Node is shared</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.directory",
            "description": "<p>Is node a collection or a file?</p>"
          }
        ],
        "200 OK - additional attributes": [
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.thumbnail",
            "description": "<p>Id of preview (file node only)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.access",
            "description": "<p>Access if node is shared, one of r/rw/w</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.shareowner",
            "description": "<p>Username of the share owner</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.parent",
            "description": "<p>ID of the parent node</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.path",
            "description": "<p>Absolute node path</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.filtered",
            "description": "<p>Node is filtered (usually only a collection)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.readonly",
            "description": "<p>Node is readonly</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "object[]",
            "optional": false,
            "field": "data.history",
            "description": "<p>Get file history (file node only)</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\":200,\n     \"data\": [{...}, {...}]\n     }\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/search"
      }
    ]
  },
  {
    "type": "get",
    "url": "/api/v1/node/share-link?id=:id",
    "title": "Get sharing link",
    "version": "1.0.6",
    "name": "getShareLink",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get an existing sharing link</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET \"https://SERVER/api/v1/node/share-link?id=544627ed3c58891f058b4686&pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/share-link?pretty\"\ncurl -XGET \"https://SERVER/api/v1/node/share-link?p=/path/to/my/node&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>Share options</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.token",
            "description": "<p>Shared unique node token</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": true,
            "field": "data.password",
            "description": "<p>Share link is password protected</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": true,
            "field": "data.expiration",
            "description": "<p>Unix timestamp</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": {\n       \"token\": \"544627ed3c51111f058b468654db6b7daca8e5.69846614\",\n    }\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/share-link?id=:id"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/node/trash",
    "title": "Get trash",
    "name": "getTrash",
    "version": "1.0.6",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>A similar endpoint to /api/v1/node/query filer={'deleted': {$type: 9}] but instead returning all deleted nodes (including children which are deleted as well) this enpoint only returns the first deleted node from every subtree)</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XGET https://SERVER/api/v1/node/trash?pretty",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": true,
            "field": "attributes",
            "description": "<p>Filter node attributes</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Children</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.id",
            "description": "<p>Unique node id</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.name",
            "description": "<p>Name</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.hash",
            "description": "<p>MD5 content checksum (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.meta",
            "description": "<p>Extended meta attributes</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.description",
            "description": "<p>UTF-8 Text Description</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.color",
            "description": "<p>Color Tag (HEX) (Like: #000000)</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.author",
            "description": "<p>Author</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.mail",
            "description": "<p>Mail contact address</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.license",
            "description": "<p>License</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.meta.copyright",
            "description": "<p>Copyright string</p>"
          },
          {
            "group": "200 OK",
            "type": "string[]",
            "optional": false,
            "field": "data.meta.tags",
            "description": "<p>Search Tags</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.size",
            "description": "<p>Size in bytes (Only file node), number of children if collection</p>"
          },
          {
            "group": "200 OK",
            "type": "string",
            "optional": false,
            "field": "data.mime",
            "description": "<p>Mime type</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.sharelink",
            "description": "<p>Is node shared?</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.version",
            "description": "<p>File version (file node only)</p>"
          },
          {
            "group": "200 OK",
            "type": "mixed",
            "optional": false,
            "field": "data.deleted",
            "description": "<p>Is boolean false if not deleted, if deleted it contains a deleted timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.deleted.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.changed",
            "description": "<p>Changed timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.changed.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "object",
            "optional": false,
            "field": "data.created",
            "description": "<p>Created timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.sec",
            "description": "<p>Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "data.created.usec",
            "description": "<p>Additional Microsecconds to Unix timestamp</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.share",
            "description": "<p>Node is shared</p>"
          },
          {
            "group": "200 OK",
            "type": "boolean",
            "optional": false,
            "field": "data.directory",
            "description": "<p>Is node a collection or a file?</p>"
          }
        ],
        "200 OK - additional attributes": [
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.thumbnail",
            "description": "<p>Id of preview (file node only)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.access",
            "description": "<p>Access if node is shared, one of r/rw/w</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.shareowner",
            "description": "<p>Username of the share owner</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.parent",
            "description": "<p>ID of the parent node</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "string",
            "optional": false,
            "field": "data.path",
            "description": "<p>Absolute node path</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.filtered",
            "description": "<p>Node is filtered (usually only a collection)</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "boolean",
            "optional": false,
            "field": "data.readonly",
            "description": "<p>Node is readonly</p>"
          },
          {
            "group": "200 OK - additional attributes",
            "type": "object[]",
            "optional": false,
            "field": "data.history",
            "description": "<p>Get file history (file node only)</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\":200,\n     \"data\": [{..}, {...}] //Shorted\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/trash"
      }
    ]
  },
  {
    "type": "head",
    "url": "/api/v1/node?id=:id",
    "title": "Node exists?",
    "version": "1.0.6",
    "name": "head",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Check if a node exists. Per default deleted nodes are ignore which means it will return a 404 if a deleted node is requested. You can change this behaviour via the deleted parameter.</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XHEAD \"https://SERVER/api/v1/node?id=544627ed3c58891f058b4686\"\ncurl -XHEAD \"https://SERVER/api/v1/node/544627ed3c58891f058b4686\"\ncurl -XHEAD \"https://SERVER/api/v1/node?p=/absolute/path/to/my/node\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "deleted",
            "defaultValue": "0",
            "description": "<p>Wherever include deleted node or not, possible values:</br></p> <ul> <li>0 Exclude deleted</br></li> <li>1 Only deleted</br></li> <li>2 Include deleted</br></li> </ul>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "success": {
      "examples": [
        {
          "title": "Success-Response (Node does exist):",
          "content": "HTTP/1.1 200 OK",
          "type": "json"
        },
        {
          "title": "Success-Response (Node does not exist):",
          "content": "HTTP/1.1 404 Not Found",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "post",
    "url": "/api/v1/node/clone?id=:id",
    "title": "Clone node",
    "version": "1.0.6",
    "name": "postClone",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Clone a node</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "destid",
            "description": "<p>Either destid or destp (path) of the new parent collection node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "destp",
            "description": "<p>Either destid or destp (path) of the new parent collection node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "conflict",
            "defaultValue": "0",
            "description": "<p>Decides how to handle a conflict if a node with the same name already exists at the destination. Possible values are:</br></p> <ul> <li>0 No action</br></li> <li>1 Automatically rename the node</br></li> <li>2 Overwrite the destination (merge)</br></li> </ul>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/node/clone?id=544627ed3c58891f058b4686&dest=544627ed3c58891f058b4676\"\ncurl -XPOST \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/clone?dest=544627ed3c58891f058b4676&conflict=2\"\ncurl -XPOST \"https://SERVER/api/v1/node/clone?p=/absolute/path/to/my/node&conflict=0&destp=/new/parent\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/clone?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Multi node error):",
          "content": "HTTP/1.1 400 Bad Request\n{\n    \"status\": 400,\n    \"data\": [\n        {\n             id: \"51354d073c58891f058b4580\",\n             name: \"file.zip\",\n             error: \"Balloon\\\\Exception\\\\Conflict\",\n             message: \"node already exists\",\n             code: 30\n        }\n    ]\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "post",
    "url": "/api/v1/node/meta-attributes?id=:id",
    "title": "Write meta attributes",
    "version": "1.0.6",
    "name": "postMetaAttributes",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get meta attributes of a node</p>",
    "parameter": {
      "fields": {
        "POST Parameter": [
          {
            "group": "POST Parameter",
            "type": "string",
            "optional": true,
            "field": "description",
            "description": "<p>UTF-8 Text Description</p>"
          },
          {
            "group": "POST Parameter",
            "type": "string",
            "optional": true,
            "field": "color",
            "description": "<p>Color Tag (HEX) (Like: #000000)</p>"
          },
          {
            "group": "POST Parameter",
            "type": "string",
            "optional": true,
            "field": "author",
            "description": "<p>Author</p>"
          },
          {
            "group": "POST Parameter",
            "type": "string",
            "optional": true,
            "field": "mail",
            "description": "<p>Mail contact address</p>"
          },
          {
            "group": "POST Parameter",
            "type": "string",
            "optional": true,
            "field": "license",
            "description": "<p>License</p>"
          },
          {
            "group": "POST Parameter",
            "type": "string",
            "optional": true,
            "field": "opyright",
            "description": "<p>Copyright string</p>"
          },
          {
            "group": "POST Parameter",
            "type": "string[]",
            "optional": true,
            "field": "tags",
            "description": "<p>Search Tags</p>"
          }
        ],
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST -d author=peter.mier -d license=\"GPLv2\" \"https://SERVER/api/v1/node/meta-attributes?id=544627ed3c58891f058b4686\"\ncurl -XPOST -d author=authorname \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/meta-attributes\"\ncurl -XPOST -d license=\"GPLv3\" \"https://SERVER/api/v1/node/meta-attributes?p=/absolute/path/to/my/node\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/meta-attributes?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "post",
    "url": "/api/v1/node/move?id=:id",
    "title": "Move node",
    "version": "1.0.6",
    "name": "postMove",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Move node</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "destid",
            "description": "<p>Either destid or destp (path) of the new parent collection node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "destp",
            "description": "<p>Either destid or destp (path) of the new parent collection node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "id",
            "description": "<p>Either a single id as string or multiple as an array or a single p (path) as string or multiple paths as array must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "p",
            "description": "<p>Either a single id as string or multiple as an array or a single p (path) as string or multiple paths as array must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "conflict",
            "defaultValue": "0",
            "description": "<p>Decides how to handle a conflict if a node with the same name already exists at the destination. Possible values are:</br></p> <ul> <li>0 No action</br></li> <li>1 Automatically rename the node</br></li> <li>2 Overwrite the destination (merge)</br></li> </ul>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/node/move?id=544627ed3c58891f058b4686?destid=544627ed3c58891f058b4655\"\ncurl -XPOST \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/move?destid=544627ed3c58891f058b4655\"\ncurl -XPOST \"https://SERVER/api/v1/node/move?p=/absolute/path/to/my/node&destp=/new/parent&conflict=1",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        },
        {
          "title": "Success-Response (conflict=1):",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\":200,\n     \"data\": \"renamed (xy23)\"\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/move?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>General error messages of type  Balloon\\Exception do not usually have an error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Multi node error):",
          "content": "HTTP/1.1 400 Bad Request\n{\n    \"status\": 400,\n    \"data\": [\n        {\n             id: \"51354d073c58891f058b4580\",\n             name: \"file.zip\",\n             error: \"Balloon\\\\Exception\\\\Conflict\",\n             message: \"node already exists\",\n             code: 30\n        }\n    ]\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "post",
    "url": "/api/v1/node/name?id=:id",
    "title": "Rename node",
    "version": "1.0.6",
    "name": "postName",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Rename a node. The characters (\\ &lt; &gt; : &quot; / * ? |) (without the &quot;()&quot;) are not allowed to use within a node name.</p>",
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "name",
            "description": "<p>The new name of the node</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "error": {
      "fields": {
        "Error 400": [
          {
            "group": "Error 400",
            "optional": false,
            "field": "Exception",
            "description": "<p>name contains invalid characters</p>"
          }
        ],
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/node/name?id=544627ed3c58891f058b4686&name=newname.txt\"\ncurl -XPOST \"https://SERVER/api/v1/node/544627ed3c58891f058b4677/name?name=newdir\"\ncurl -XPOST \"https://SERVER/api/v1/node/name?p=/absolute/path/to/my/node&name=newname.txt\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/name?id=:id"
      }
    ]
  },
  {
    "type": "post",
    "url": "/api/v1/node/readonly?id=:id",
    "title": "Mark node as readonly",
    "version": "1.0.6",
    "name": "postReadonly",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Mark (or unmark) node as readonly</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/node/readonly?id[]=544627ed3c58891f058b4686&id[]=544627ed3c58891f058b46865&readonly=1\"\ncurl -XPOST \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/readonly?readonly=0\"\ncurl -XPOST \"https://SERVER/api/v1/node/readonly?p=/absolute/path/to/my/node\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "bool",
            "optional": true,
            "field": "readonly",
            "defaultValue": "true",
            "description": "<p>Set readonly to false to make node writeable again</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "id",
            "description": "<p>Either a single id as string or multiple as an array or a single p (path) as string or multiple paths as array must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "p",
            "description": "<p>Either a single id as string or multiple as an array or a single p (path) as string or multiple paths as array must be given.</p>"
          }
        ]
      }
    },
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/readonly?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>General error messages of type  Balloon\\Exception do not usually have an error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Multi node error):",
          "content": "HTTP/1.1 400 Bad Request\n{\n    \"status\": 400,\n    \"data\": [\n        {\n             id: \"51354d073c58891f058b4580\",\n             name: \"file.zip\",\n             error: \"Balloon\\\\Exception\\\\Conflict\",\n             message: \"node already exists\",\n             code: 30\n        }\n    ]\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "post",
    "url": "/api/v1/node/share-link?id=:id",
    "title": "Create sharing link",
    "version": "1.0.6",
    "name": "postShareLink",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Create a unique sharing link of a node (global accessible): a possible existing link will be deleted if this method will be called.</p>",
    "parameter": {
      "fields": {
        "POST Parameter": [
          {
            "group": "POST Parameter",
            "type": "object",
            "optional": true,
            "field": "options",
            "description": "<p>Sharing options</p>"
          },
          {
            "group": "POST Parameter",
            "type": "number",
            "optional": true,
            "field": "options.expiration",
            "description": "<p>Expiration unix timestamp of the sharing link</p>"
          },
          {
            "group": "POST Parameter",
            "type": "string",
            "optional": true,
            "field": "options.password",
            "description": "<p>Protected shared link with password</p>"
          }
        ],
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "id",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": false,
            "field": "p",
            "description": "<p>Either id or p (path) of a node must be given.</p>"
          }
        ]
      }
    },
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/node/share-link?id=544627ed3c58891f058b4686&pretty\"\ncurl -XPOST \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/share-link?pretty\"\ncurl -XPOST \"https://SERVER/api/v1/node/share-link?p=/absolute/path/to/my/node&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response (Created or modified share link):",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/share-link?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>Error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "post",
    "url": "/api/v1/node/undelete?id=:id",
    "title": "Undelete node",
    "version": "1.0.6",
    "name": "postUndelete",
    "group": "Node",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Undelete (Restore from trash) a single node or multiple ones.</p>",
    "examples": [
      {
        "title": "(cURL) example:",
        "content": "curl -XPOST \"https://SERVER/api/v1/node/undelete?id[]=544627ed3c58891f058b4686&id[]=544627ed3c58891f058b46865&pretty\"\ncurl -XPOST \"https://SERVER/api/v1/node/undelete?id=544627ed3c58891f058b4686?pretty\"\ncurl -XPOST \"https://SERVER/api/v1/node/544627ed3c58891f058b4686/undelete?conflict=2\"\ncurl -XPOST \"https://SERVER/api/v1/node/undelete?p=/absolute/path/to/my/node&conflict=0&move=1&destid=544627ed3c58891f058b46889\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "destid",
            "description": "<p>Either destid or destp (path) of the new parent collection node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "destp",
            "description": "<p>Either destid or destp (path) of the new parent collection node must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "id",
            "description": "<p>Either a single id as string or multiple as an array or a single p (path) as string or multiple paths as array must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "p",
            "description": "<p>Either a single id as string or multiple as an array or a single p (path) as string or multiple paths as array must be given.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": true,
            "field": "conflict",
            "defaultValue": "0",
            "description": "<p>Decides how to handle a conflict if a node with the same name already exists at the destination. Possible values are:</br></p> <ul> <li>0 No action</br></li> <li>1 Automatically rename the node</br></li> <li>2 Overwrite the destination (merge)</br></li> </ul>"
          }
        ]
      }
    },
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        },
        {
          "title": "Success-Response (conflict=1):",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\":200,\n     \"data\": \"renamed (xy23)\"\n     }\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Node.php",
    "groupTitle": "Node",
    "sampleRequest": [
      {
        "url": " /api/v1/node/undelete?id=:id"
      }
    ],
    "error": {
      "fields": {
        "General Error Response": [
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "General Error Response",
            "type": "object[]",
            "optional": false,
            "field": "data",
            "description": "<p>Error body</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.error",
            "description": "<p>Exception</p>"
          },
          {
            "group": "General Error Response",
            "type": "string",
            "optional": false,
            "field": "data.message",
            "description": "<p>Message</p>"
          },
          {
            "group": "General Error Response",
            "type": "number",
            "optional": false,
            "field": "data.code",
            "description": "<p>General error messages of type  Balloon\\Exception do not usually have an error code</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Error-Response (Invalid Parameter):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"invalid node id specified\",\n         \"code\": 0\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Insufficient Access):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"not allowed to read node 51354d073c58891f058b4580\",\n         \"code\": 40\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"node 51354d073c58891f058b4580 not found\",\n         \"code\": 49\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Multi node error):",
          "content": "HTTP/1.1 400 Bad Request\n{\n    \"status\": 400,\n    \"data\": [\n        {\n             id: \"51354d073c58891f058b4580\",\n             name: \"file.zip\",\n             error: \"Balloon\\\\Exception\\\\Conflict\",\n             message: \"node already exists\",\n             code: 30\n        }\n    ]\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Conflict):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"a node called myname does already exists\",\n         \"code\": 17\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/resource/acl-roles?q=:query&namespace=:namespace",
    "title": "Query available acl roles",
    "version": "1.0.6",
    "name": "getAclRoles",
    "group": "Resource",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Query available acl roles (user and groups)</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1/user/acl-roles?q=peter&namespace=organization&pretty\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string",
            "optional": true,
            "field": "1",
            "description": "<p>Search query (user/group)</p>"
          },
          {
            "group": "GET Parameter",
            "type": "boolean",
            "optional": true,
            "field": "single",
            "description": "<p>Search request for a single user (Don't have to be in namespace)</p>"
          }
        ]
      }
    },
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "object[]",
            "optional": false,
            "field": "roles",
            "description": "<p>All roles found with query search string</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "roles.type",
            "description": "<p>ACL role type (user|group)</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "roles.id",
            "description": "<p>Role identifier (Could be the same as roles.name)</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "roles.name",
            "description": "<p>Role name (human readable name)</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": [\n         {\n             \"type\": \"user\",\n             \"id\": \"peter.meier\",\n             \"name\": \"peter.meier\"\n         },\n         {\n             \"type\": \"group\",\n             \"id\": \"peters\",\n             \"name\": \"peters\"\n         }\n     ]\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Resource.php",
    "groupTitle": "Resource",
    "sampleRequest": [
      {
        "url": " /resource/acl-roles?q=:query&namespace=:namespace"
      }
    ]
  },
  {
    "type": "get",
    "url": "/",
    "title": "Server & API Status",
    "version": "1.0.6",
    "name": "get",
    "group": "Rest",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get server time and api status/version</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1?pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>API/Server information</p>"
          },
          {
            "group": "Success 200",
            "type": "float",
            "optional": false,
            "field": "data.version",
            "description": "<p>API Version</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.server_timestamp",
            "description": "<p>Server timestamp in unix format (seconds since 1970-01-01 00:00:00)</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.server_timezone",
            "description": "<p>Server timezone</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": {\n        \"version\": 1,\n        \"server_timestamp\": 1423660181,\n        \"server_timezone\": \"Europe\\/Berlin\",\n    }\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Rest.php",
    "groupTitle": "Rest",
    "sampleRequest": [
      {
        "url": " /"
      }
    ]
  },
  {
    "type": "get",
    "url": "/about",
    "title": "API Information",
    "version": "1.0.6",
    "name": "getAbout",
    "group": "Rest",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get various API information</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1/about?pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>API information</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.description",
            "description": "<p>API description</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.copyright",
            "description": "<p>Copyright</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data.license",
            "description": "<p>License</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": {\n         \"description\": \"This is the balloon API Interface...\",\n         \"copyright\": \"gyselroth Gmbh 2012 - 2016\",\n         \"license\": \"GPLv3\",\n    }\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Rest.php",
    "groupTitle": "Rest",
    "sampleRequest": [
      {
        "url": " /about"
      }
    ]
  },
  {
    "type": "get",
    "url": "/help",
    "title": "API Help Reference",
    "version": "1.0.6",
    "name": "getAbout",
    "group": "Rest",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>API realtime reference (Automatically search all possible API methods)</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1/help?pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>API Reference</p>"
          }
        ]
      }
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Rest.php",
    "groupTitle": "Rest",
    "sampleRequest": [
      {
        "url": " /help"
      }
    ]
  },
  {
    "type": "get",
    "url": "/version",
    "title": "API Version",
    "version": "1.0.6",
    "name": "getVersion",
    "group": "Rest",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get API Version</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/version?pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data",
            "description": "<p>API version</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": 1\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Rest.php",
    "groupTitle": "Rest",
    "sampleRequest": [
      {
        "url": " /version"
      }
    ]
  },
  {
    "type": "delete",
    "url": "/api/v1/user?uid=:uid",
    "title": "Delete user",
    "version": "1.0.6",
    "name": "delete",
    "group": "User",
    "permission": [
      {
        "name": "admin"
      }
    ],
    "description": "<p>Delete user account, this will also remove any data owned by the user. If force is false, all data gets moved to the trash. If force is true all data owned by the user gets ereased.</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XDELETE \"https://SERVER/api/v1/user/544627ed3c58891f058b4611?force=1\"\ncurl -XDELETE \"https://SERVER/api/v1/user?uname=loginuser\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "bool",
            "optional": true,
            "field": "force",
            "defaultValue": "false",
            "description": "<p>Per default the user account will be disabled, if force is set the user account gets removed completely.</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (Can not delete yourself):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Conflict\",\n         \"message\": \"requested user was not found\"\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    },
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Admin/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user?uid=:uid"
      }
    ]
  },
  {
    "type": "get",
    "url": "/api/v1/user/attributes",
    "title": "User attributes",
    "version": "1.0.6",
    "name": "getAttributes",
    "group": "User",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get all user attributes including username, mail, id,.... If you want to receive your own attributes you have to leave the parameters uid and uname empty. Requesting this api with parameter uid or uname requires admin privileges.</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1/user/attributes?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/544627ed3c58891f058b4611/attributes?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/attributes?uname=loginser&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "200 OK": [
          {
            "group": "200 OK",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "200 OK",
            "type": "object[]",
            "optional": false,
            "field": "user",
            "description": "<p>attributes</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n     \"status\": 200,\n     \"data\": [] //shortened\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user/attributes"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/user/groups",
    "title": "Group membership",
    "version": "1.0.6",
    "name": "getGroups",
    "group": "User",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get all user groups If you want to receive your own groups you have to leave the parameters uid and uname empty. Requesting this api with parameter uid or uname requires admin privileges.</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1/user/groups?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/544627ed3c58891f058b4611/groups?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/groups?uname=loginuser&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "string[]",
            "optional": false,
            "field": "data",
            "description": "<p>All groups with membership</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": [\n         \"group1\",\n         \"group2\",\n    ]\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user/groups"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/user/is-admin",
    "title": "Is Admin?",
    "version": "1.0.6",
    "name": "getIsAdmin",
    "group": "User",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Check if the authenicated user has admin rights. If you want to check your own admin status you have to leave the parameters uid and uname empty. Requesting this api with parameter uid or uname requires admin privileges.</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1/user/is-admin\"\ncurl -XGET \"https://SERVER/api/v1/user/544627ed3c58891f058b4611/is-admin\"\ncurl -XGET \"https://SERVER/api/v1/user/is-admin?uname=loginuser\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "boolean",
            "optional": false,
            "field": "data",
            "description": "<p>TRUE if admin</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": true\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user/is-admin"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/user/node-attribute-summary",
    "title": "Node attribute summary",
    "version": "1.0.6",
    "name": "getNodeAttributeSummary",
    "group": "User",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get summary and usage of specific node attributes If you want to receive your own node summary you have to leave the parameters uid and uname empty. Requesting this api with parameter uid or uname requires admin privileges.</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1/user/node-attribute-summary?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/544627ed3c58891f058b4611/node-attribute-summary?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/node-attribute-summary?uname=loginuser&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>The username</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": [...]\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user/node-attribute-summary"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/user/quota-usage",
    "title": "Quota usage",
    "version": "1.0.6",
    "name": "getQuotaUsage",
    "group": "User",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get user quota usage (including hard,soft,used and available space). If you want to receive your own quota you have to leave the parameters uid and uname empty. Requesting this api with parameter uid or uname requires admin privileges.</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1/user/quota-usage?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/544627ed3c58891f058b4611/quota-usage?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/quota-usage?uname=loginuser&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "object",
            "optional": false,
            "field": "data",
            "description": "<p>Quota stats</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.used",
            "description": "<p>Used quota in bytes</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.available",
            "description": "<p>Quota left in bytes</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.hard_quota",
            "description": "<p>Hard quota in bytes</p>"
          },
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "data.soft_quota",
            "description": "<p>Soft quota (Warning) in bytes</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": {\n        \"used\": 15543092,\n        \"available\": 5353166028,\n        \"hard_quota\": 5368709120,\n        \"soft_quota\": 5368709120\n    }\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user/quota-usage"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/user/shares",
    "title": "Share membership",
    "version": "1.0.6",
    "name": "getShares",
    "group": "User",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get all shares If you want to receive your own shares (member or owner) you have to leave the parameters uid and uname empty. Requesting this api with parameter uid or uname requires admin privileges.</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1/user/shares?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/544627ed3c58891f058b4611/shares?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/shares?uname=loginuser&pretty\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "string[]",
            "optional": false,
            "field": "data",
            "description": "<p>All shares with membership</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": [\n         \"shareid1\",\n         \"shareid2\",\n    ]\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user/shares"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "get",
    "url": "/api/v1/user/whoami",
    "title": "Who am I?",
    "version": "1.0.6",
    "name": "getWhoami",
    "group": "User",
    "permission": [
      {
        "name": "none"
      }
    ],
    "description": "<p>Get the username of the authenticated user If you want to receive your own username you have to leave the parameters uid and uname empty. Requesting this api with parameter uid or uname requires admin privileges.</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XGET \"https://SERVER/api/v1/user/whoami?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/544627ed3c58891f058b4611/whoami?pretty\"\ncurl -XGET \"https://SERVER/api/v1/user/whoami?uname=loginuser\"",
        "type": "json"
      }
    ],
    "success": {
      "fields": {
        "Success 200": [
          {
            "group": "Success 200",
            "type": "number",
            "optional": false,
            "field": "status",
            "description": "<p>Status Code</p>"
          },
          {
            "group": "Success 200",
            "type": "string",
            "optional": false,
            "field": "data",
            "description": "<p>The username</p>"
          }
        ]
      },
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 200 OK\n{\n    \"status\": 200,\n    \"data\": \"peter.meier\"\n}",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user/whoami"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "post",
    "url": "/api/v1/user/quota?uid=:uid",
    "title": "Set quota",
    "version": "1.0.6",
    "name": "postQuota",
    "group": "User",
    "permission": [
      {
        "name": "admin"
      }
    ],
    "description": "<p>Set quota for user</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XPOST -d hard=10000000 -d soft=999999 \"https://SERVER/api/v1/user/quota\"\ncurl -XPOST -d hard=10000000 -d soft=999999 \"https://SERVER/api/v1/user/544627ed3c58891f058b4611/quota\"\ncurl -XPOST -d hard=10000000 -d soft=999999 \"https://SERVER/api/v1/user/quota?uname=loginuser\"",
        "type": "json"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "hard",
            "description": "<p>The new hard quota in bytes</p>"
          },
          {
            "group": "GET Parameter",
            "type": "number",
            "optional": false,
            "field": "soft",
            "description": "<p>The new soft quota in bytes</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Admin/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user/quota?uid=:uid"
      }
    ],
    "error": {
      "examples": [
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "head",
    "url": "/api/v1/user?uid=:uid",
    "title": "User exists?",
    "version": "1.0.6",
    "name": "postQuota",
    "group": "User",
    "permission": [
      {
        "name": "admin"
      }
    ],
    "description": "<p>Check if user account exists</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XHEAD \"https://SERVER/api/v1/user\"\ncurl -XHEAD \"https://SERVER/api/v1/user/544627ed3c58891f058b4611\"\ncurl -XHEAD \"https://SERVER/api/v1/user?uname=loginuser\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Admin/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user?uid=:uid"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    }
  },
  {
    "type": "post",
    "url": "/api/v1/user/undelete?uid=:uid",
    "title": "Restore user",
    "version": "1.0.6",
    "name": "postUndelete",
    "group": "User",
    "permission": [
      {
        "name": "admin"
      }
    ],
    "description": "<p>Restore user account. This endpoint does not restore any data, it only does reactivate an existing user account.</p>",
    "examples": [
      {
        "title": "Example usage:",
        "content": "curl -XPOST \"https://SERVER/api/v1/user/544627ed3c58891f058b4611/undelete\"\ncurl -XPOST \"https://SERVER/api/v1/user/undelete?user=loginuser\"",
        "type": "json"
      }
    ],
    "success": {
      "examples": [
        {
          "title": "Success-Response:",
          "content": "HTTP/1.1 204 No Content",
          "type": "json"
        }
      ]
    },
    "filename": "/home/users/raffael.sahli/github/balloon/src/lib/Balloon/Rest/v1/Admin/User.php",
    "groupTitle": "User",
    "sampleRequest": [
      {
        "url": " /api/v1/user/undelete?uid=:uid"
      }
    ],
    "parameter": {
      "fields": {
        "GET Parameter": [
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uid",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          },
          {
            "group": "GET Parameter",
            "type": "string[]",
            "optional": false,
            "field": "uname",
            "description": "<p>Either a single uid (user id) or a uname (username) must be given (admin privilege required).</p>"
          }
        ]
      }
    },
    "error": {
      "examples": [
        {
          "title": "Error-Response (No admin privileges):",
          "content": "HTTP/1.1 403 Forbidden\n{\n     \"status\": 403,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\Forbidden\",\n         \"message\": \"submitted parameters require to have admin privileges\",\n         \"code\": 41\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (User not found):",
          "content": "HTTP/1.1 404 Not Found\n{\n     \"status\": 404,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\NotFound\",\n         \"message\": \"requested user was not found\",\n         \"code\": 53\n     }\n}",
          "type": "json"
        },
        {
          "title": "Error-Response (Invalid argument):",
          "content": "HTTP/1.1 400 Bad Request\n{\n     \"status\": 400,\n     \"data\": {\n         \"error\": \"Balloon\\\\Exception\\\\InvalidArgument\",\n         \"message\": \"provide either uid (user id) or uname (username)\",\n         \"Code\": 0\n     }\n}",
          "type": "json"
        }
      ]
    }
  }
] });
