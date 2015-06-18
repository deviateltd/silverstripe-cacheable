<ul>    
    <li>Cache directory: $FileCacheDir</li>
    <li>Total files: $FileTotal</li>
    <li>Last updated: $CacheLastEdited</li>
    <li>Size on disk: $FileSizeOnDisk</li>
</ul>
<ul>
    <% loop $FileList %>
    <li><code>$Line</code></li>
    <% end_loop %>
</ul>