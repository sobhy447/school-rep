using Atlas.MultiTenancy.Abstractions;
using Atlas.MultiTenancy.Context;
using Atlas.MultiTenancy.Exceptions;
using Atlas.MultiTenancy.Options;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Options;

namespace Atlas.MultiTenancy.Middleware;

public sealed class TenantResolutionMiddleware(
    ICurrentTenant currentTenant,
    ITenantResolver tenantResolver,
    IOptions<MultiTenancyOptions> options) : IMiddleware
{
    private readonly MultiTenancyOptions _options = options.Value;

    public async Task InvokeAsync(HttpContext context, RequestDelegate next)
    {
        if (IsBypassed(context.Request.Path))
        {
            await next(context);
            return;
        }

        long? tenantId = await tenantResolver.ResolveAsync(context.RequestAborted);

        if (tenantId is not { } resolvedTenantId)
        {
            throw new TenantResolutionException(
                $"Unable to resolve a tenant for request '{context.Request.Method} {context.Request.Path}'.");
        }

        using TenantScope scope = currentTenant.Change(resolvedTenantId);

        await next(context);
    }

    private bool IsBypassed(PathString path) =>
        _options.BypassPaths.Count != 0 &&
        _options.BypassPaths.Any(bypassPath =>
            path.StartsWithSegments(bypassPath, StringComparison.OrdinalIgnoreCase));
}
