<aside class="aside">
<ul>
    <% with $CachedData %>
        <% loop $Menu(2) %>
            <li><% include CachedNavigationItem %></li>
        <% end_loop %>
    <% end_with %>
</ul>
</aside>