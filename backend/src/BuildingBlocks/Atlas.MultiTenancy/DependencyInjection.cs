using Atlas.MultiTenancy.Abstractions;
using Atlas.MultiTenancy.Context;
using Atlas.MultiTenancy.Middleware;
using Atlas.MultiTenancy.Options;
using Atlas.MultiTenancy.Resolution;
using Microsoft.AspNetCore.Builder;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection.Extensions;

namespace Atlas.MultiTenancy;

public static class DependencyInjection
{
    public static IServiceCollection AddMultiTenancy(
        this IServiceCollection services,
        Action<MultiTenancyOptions>? configure = null)
    {
        services.AddHttpContextAccessor();

        OptionsBuilder<MultiTenancyOptions> optionsBuilder = services.AddOptions<MultiTenancyOptions>();

        if (configure is not null)
        {
            optionsBuilder.Configure(configure);
        }

        optionsBuilder
            .Validate(
                options => !string.IsNullOrWhiteSpace(options.TenantClaimType),
                "MultiTenancy: TenantClaimType must be configured.")
            .Validate(
                options => !options.AllowHeaderResolution || !string.IsNullOrWhiteSpace(options.TenantHeaderName),
                "MultiTenancy: TenantHeaderName must be configured when header resolution is enabled.")
            .ValidateOnStart();

        services.TryAddSingleton<TenantContextAccessor>();
        services.TryAddSingleton<ITenantContext>(provider => provider.GetRequiredService<TenantContextAccessor>());
        services.TryAddSingleton<ICurrentTenant>(provider => provider.GetRequiredService<TenantContextAccessor>());
        services.TryAddScoped<ITenantResolver, HttpContextTenantResolver>();
        services.TryAddScoped<TenantResolutionMiddleware>();

        return services;
    }

    public static IApplicationBuilder UseMultiTenancy(this IApplicationBuilder app) =>
        app.UseMiddleware<TenantResolutionMiddleware>();
}
