<div id="sections">
<section id="api-general">
<h1>General</h1>
<div id="api-general-auth">

<article>
<div class="pull-left">
<h1>Getting Started</h1>
</div>
<div class="clearfix"></div>
<p>
Balloon has a full featured HTTP API. You can do pretty much everything with the API. This documentation represents an API
reference which you can use to include Balloon within your application or even create a new Balloon client app on your own.
This API speaks GET, POST, PUT, DELETE and HEAD with you, just select the right HTTP verb for your needs.
</p>

<p>
The server API core is trimmed to high performance and will process your requests within a few milliseconds.
But this is somehow related with your request. Not every type of request performs as good as others. 
For example, pretty much every action requires either a parameter "id" or a parameter "p" (which stands for path).
Requests with "path" would take longer, since the server needs to resolve the path until your requsted node is found.
While you pass an id instead a path, the server is able to request the node 1:1 from its backend database. 
As you can see, if it is possible, you should go with id and forget about file paths. There are also some parameters which 
can reduce performance for example if your attach the "pretty" parameter to a request the server will perform slower than without 
or if your request more attributes like "path".
</p>

<p>
This reference does use a placeholder called https://SERVER/api/v1, whereas SERVER should be replaced with your actual server name.
The Balloon API is always placed under /api on the server. So an example URL could be,
https://mycloud.com/api/v1.
</p>
</article>

<article>
<div class="pull-left">
<h1>Authentication</h1>
</div>
<div class="clearfix"></div>
<p>
You can choose between two authentication methods. The first one is the classical basic HTTP authentication. With this kind of method you have to send the credentials
(username / password) base64 encoded in a header called Authorization.
Since this is a stateless HTTP API you will need to send the authentication information with every request.
</p>
<pre class="prettyprint prettyprinted" style=""><code>curl -u "username:password" https://SERVER/api/v1
#Curl builds the basic http auth header automatically if you post your credentials within the option -u
#This is pretty much the same as curl -H "Authorization: Basic $(echo username:password | base64 -e)" https://SERVER/api/v1</code></pre>

<p>
The seccond possible authentication method is OAUTH2. The big adventages of OAUTH2 is, you don't have to send username/password with every request. Instead you have to send an access token, 
which allows a user to use a service. In this case the service Balloon. This type of token is an HTTP Bearer token, which you have to attach to the http stack.
</p>
<pre class="prettyprint prettyprinted" style=""><code>curl -H "Authorization: Bearer access_token" https://SERVER/api/v1
#While access_token is your valid access_token, which you get from your OAUTH2 authorization server.</code></pre>
<p>
In both cases you should get back HTTP OK 200 from https://SERVER/api/v1. If this is not the case, you're doing something wrong.
The HTTP basic authentication method is way more simpler, but if you have the choice and it does not matter which one you choose, 
then you should go with OAUTH2. It is more secure and there are no credentials attached to requests on the wire.
</p>
</article>

<article>
<div class="pull-left">
<h1>Response</h1>
</div>
<div class="clearfix"></div>
<p>
The  API is able to respond with two different formats. You can either request a JSON based body or an XML one.
JSON is normally preffered and is set as the default. So you don't have to specify the Accept header if you which to receive JSON.

For request an XML based response, your can attach an Accept header to your request:
</p>
<pre class="prettyprint prettyprinted" style=""><code>curl -H 'Accept: application/xml' https://SERVER/api/v1</code></pre>

<p>
You can even request both with a nicely formatted output:
(But keep in mind that this will use more calculation time on the API server, you should not use this parameter within your stable application)
</p>
<pre class="prettyprint prettyprinted" style=""><code>curl -H 'Accept: application/xml' https://SERVER/api/v1?pretty
curl -H 'Accept: application/json' https://SERVER/api/v1?pretty
</code></pre>

</article>


<article>
<div class="pull-left">
<h1>Request</h1>
</div>
<div class="clearfix"></div>
<p>
Instead attaching your parameters to the query string, you can send them as a JSON (or of course XML as you wish) attached to your API request. You just need to specify that you 
send actually JSON or XML to the API. In that case you have to specify the Content-Type header.
</p>
<pre class="prettyprint prettyprinted" style=""><code>curl -u "user:pw" -XGET -H 'Content-Type: application/json' https://SERVER/api/v1/collection/children 
-d '{"attributes":["mime"]}'</code></pre>
</article>



<article>
<div class="pull-left">
<h1>Exception-Handling</h1>
</div>
<div class="clearfix"></div>
<p>

<p>
The Api will throw various different Exceptions if they can not be handled by the application itself.
Exception response primarily come with an HTTP status code of 4xx or 5xx. A successful response usually comes
with HTTP 2xx.

An error response always comes with an HTTP status code, an error message, the exception type and an error code.
(Exceptions of type \Balloon\Exception usually do not have an error code). It can also bet that multiple exceptions get thrown
an the error response will contain an array of exceptions. This will be the case if the API gets requested with actions for multiple resources
instead just one.
</p>

<h5>Example of an exception of type \Balloon\Exception\NotFound response</h5>
<pre class="prettyprint prettyprinted" style=""><code>HTTP/1.1 404 Not Found
{
    "status": 404,
    "data": {
        "error": "Balloon\\Exception\\NotFound",
        "message": "node 51354d073c58891f058b4580 not found",
        "code": 50
    }
}
</code></pre>

<h5>Example of an exception of type \Balloon\Exception\Conflict from a multi resouce request</h5>
<pre class="prettyprint prettyprinted" style=""><code>HTTP/1.1 400 Bad Request
{
    "status": 400,
    "data": [
        {
             id: "51354d073c58891f058b4580",
             name: "file.zip",
             error: "Balloon\\Exception\\Conflict",
             message: "node already exists",
             code: 30
        }
    ]
}
</code></pre>

<h5>Custom error codes for exceptions of type \Balloon\Exception\Conflict</h5>
<p>Exceptions with type \Balloon\Exception\Conflict will come with an HTTP error code 400</p>
<ul>
     <li>ALREADY_THERE                      = 0x11</li>
     <li>CANT_BE_CHILD_OF_ITSELF            = 0x12</li>
     <li>NODE_WITH_SAME_NAME_ALREADY_EXISTS = 0x13</li> 
     <li>SHARED_NODE_CANT_BE_CHILD_OF_SHARE = 0x14</li> 
     <li>DELETED_PARENT                     = 0x15</li> 
     <li>NODE_CONTAINS_SHARED_NODE          = 0x16</li> 
     <li>PARENT_NOT_AVAILABLE_ANYMORE       = 0x17</li> 
     <li>NOT_DELETED                        = 0x18</li>
     <li>READONLY                           = 0x19</li> 
     <li>CANT_COPY_INTO_ITSELF              = 0x110</li> 
     <li>NOT_SHARED                         = 0x111</li> 
     <li>CAN_NOT_DELETE_OWN_ACCOUNT         = 0x112</li> 
     <li>CHUNKS_LOST                        = 0x113</li>
     <li>CHUNKS_INVALID_SIZE                = 0x114</li>
     <li>INVALID_OFFSET                     = 0x115</li>
</ul>

<h5>Custom error codes for exceptions of type \Balloon\Exception\Forbidden</h5>
<p>Exceptions with type \Balloon\Exception\Forbidden will come with an HTTP error code 403</p>
<ul>
     <li>NOT_ALLOWED_TO_RESTORE   = 0x21</li>
     <li>NOT_ALLOWED_TO_DELETE    = 0x22</li>
     <li>NOT_ALLOWED_TO_MODIFY    = 0x23</li>
     <li>NOT_ALLOWED_TO_OVERWRITE = 0x24</li>
     <li>NOT_ALLOWED_TO_SHARE     = 0x25</li>
     <li>NOT_ALLOWED_TO_CREATE    = 0x26</li>
     <li>NOT_ALLOWED_TO_MOVE      = 0x27</li>
     <li>NOT_ALLOWED_TO_ACCESS    = 0x28</li>
     <li>ADMIN_PRIV_REQUIRED      = 0x29</li>
     <li>NOT_ALLOWED_TO_UNDELETE  = 0x210</li>
</ul>

<h5>Custom error codes for exceptions of type \Balloon\Exception\NotFound</h5>
<p>Exceptions with type \Balloon\Exception\NotFound will come with an HTTP error code 404</p>
<ul>
     <li>NODE_NOT_FOUND        = 0x31</li> 
     <li>SHARE_NOT_FOUND       = 0x32</li>
     <li>REFERENCE_NOT_FOUND   = 0x33</li> 
     <li>NOT_ALL_NODES_FOUND   = 0x34</li>
     <li>USER_NOT_FOUND        = 0x35</li> 
     <li>DESTINTAION_NOT_FOUND = 0x36</li> 
     <li>PARENT_NOT_FOUND      = 0x37</li> 
     <li>PREVIEW_NOT_FOUND     = 0x38</li> 
     <li>CONTENTS_NOT_FOUND    = 0x39</li> 
</ul>
</article>



</div>
</section>
</div>
