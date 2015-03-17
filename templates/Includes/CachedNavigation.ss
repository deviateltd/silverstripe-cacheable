<% with $CachedData %>
    <ul>
        <% loop $Menu(1) %>
            <li><% include CachedNavigationItem %></li>
        <% end_loop %>
    </ul>
<% end_with %>






