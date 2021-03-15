{if isset($host.comment)}

# {$host.comment}
{/if}
host-record={$address}{if !empty($aliases)},{','|implode:$aliases}{/if},{$ip}
{if $addresses}
address=/{'/'|implode:$addresses}/{$ip}
{/if}
