<a class="$LinkOrSection $LinkingMode $LinkOrCurrent" href="$Link">$Title</a>
<% if $Children %>
    <ul>
        <% loop $Children %>
            <li><% include CachedNavigationItem %></li>
        <% end_loop %>
    </ul>
<% end_if %>