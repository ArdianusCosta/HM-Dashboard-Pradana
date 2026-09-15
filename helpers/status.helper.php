<?php
function renderStatusBadge(int $status, array $stat): string
{
    $colors = [
        0 => '#6c757d', // secondary (gray)
        1 => '#1a237e', // sky blue
        2 => '#4fc3f7', // navy blue
        3 => '#ffc107', // warning (yellow)
        4 => '#dc3545', // danger (red)
        5 => '#28a745'  // success (green)
    ];

    $label = $stat[$status] ?? 'Unknown';
    $color = $colors[$status] ?? '#343a40'; // fallback dark

    return "<span class='badge p-2 badge-status' style='background-color: {$color}; color: #fff;'>{$label}</span>";
}
?>
