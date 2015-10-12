<!DOCTYPE html>
<html>
    <head>
        <title>Cacheable Object Cache Status</title>
        <style>
        html > body {
            margin: 0;
            padding: 0;
            font-family: courier;
            background: #000 !important; /* Darn debug.css */
            color: #FFF;
        }
        ul, li {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .is-ok {
            color: #FFF;
        }
        .is-fail {
            color: #DF1C00;
        }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <h2>Cache backend: $BackEndMode</h2>
            <h2>Cache status: (Cache / DB)</h2>
            <ul>
                <% loop $StatusList %>
                <li><code>$Status<% if $Missed %>. IDs that Caches missed: $Missed<% end_if %></code></li>
                <% end_loop %>
            </ul>
            $BackEndDataList
        </div>
    </body>
</html>
