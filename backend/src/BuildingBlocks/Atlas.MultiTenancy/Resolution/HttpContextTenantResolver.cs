using System.Globalization;
using System.Security.Claims;
using Atlas.MultiTenancy.Abstractions;
using Atlas.MultiTenancy.Options;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Options;

namespace Atlas.MultiTenancy.Resolution;

public sealed class HttpContextTenantResolver(
    IHttpContextAccessor httpContextAccessor,
    IOptions<MultiTenancyOptions> options) : ITenantResolver
{
    private readonly MultiTenancyOptions _options = options.Value;

    public ValueTask<long?> ResolveAsync(CancellationToken cancellationToken = default)
    {
        HttpContext? httpContext = httpContextAccessor.HttpContext;

        if (httpContext is null)
        {
            return ValueTask.FromResult<long?>(null);
        }

        if (TryResolveFromClaim(httpContext.User, out long fromClaim))
        {
            return ValueTask.FromResult<long?>(fromClaim);
        }

        if (TryResolveFromHeader(httpContext.Request.Headers, out long fromHeader))
        {
            return ValueTask.FromResult<long?>(fromHeader);
        }

        return ValueTask.FromResult<long?>(null);
    }

    private bool TryResolveFromClaim(ClaimsPrincipal principal, out long tenantId)
    {
        string? value = principal.FindFirst(_options.TenantClaimType)?.Value;
        return TryParseTenantId(value, out tenantId);
    }

    private bool TryResolveFromHeader(IHeaderDictionary headers, out long tenantId)
    {
        tenantId = default;

        if (!_options.AllowHeaderResolution)
        {
            return false;
        }

        return headers.TryGetValue(_options.TenantHeaderName, out var values) &&
               TryParseTenantId(values.ToString(), out tenantId);
    }

    private static bool TryParseTenantId(string? value, out long tenantId)
    {
        if (long.TryParse(value, NumberStyles.Integer, CultureInfo.InvariantCulture, out tenantId) &&
            tenantId > 0)
        {
            return true;
        }

        tenantId = default;
        return false;
    }
}
