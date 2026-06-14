using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Messaging.Outbox.Abstractions;
using Atlas.Persistence.PostgreSql.Context;
using Atlas.Persistence.PostgreSql.Interceptors;
using Atlas.Persistence.PostgreSql.Options;
using Atlas.Persistence.PostgreSql.Outbox;
using Atlas.Persistence.PostgreSql.UnitOfWork;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Diagnostics;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection.Extensions;
using Microsoft.Extensions.Options;

namespace Atlas.Persistence.PostgreSql;

public static class DependencyInjection
{
    public static IServiceCollection AddAtlasPersistence(
        this IServiceCollection services,
        Action<PostgreSqlOptions> configure)
    {
        ArgumentNullException.ThrowIfNull(configure);

        services.AddOptions<PostgreSqlOptions>()
            .Configure(configure)
            .Validate(
                options => !string.IsNullOrWhiteSpace(options.ConnectionString),
                "PostgreSql: ConnectionString must be configured.")
            .Validate(
                options => !string.IsNullOrWhiteSpace(options.Schema),
                "PostgreSql: Schema must be configured.")
            .Validate(
                options => options.MaxRetryCount >= 0,
                "PostgreSql: MaxRetryCount cannot be negative.")
            .Validate(
                options => options.CommandTimeoutSeconds > 0,
                "PostgreSql: CommandTimeoutSeconds must be greater than zero.")
            .ValidateOnStart();

        services.TryAddSingleton(TimeProvider.System);

        services.AddScoped<IInterceptor, TenantSessionInterceptor>();
        services.AddScoped<IInterceptor, AuditingInterceptor>();
        services.AddScoped<IInterceptor, SoftDeleteInterceptor>();
        services.AddScoped<IInterceptor, EntityVersionInterceptor>();
        services.AddScoped<IInterceptor, DomainEventCollectionInterceptor>();

        services.TryAddScoped<IOutboxWriter, OutboxWriter>();

        return services;
    }

    public static IServiceCollection AddAtlasDbContext<TContext>(this IServiceCollection services)
        where TContext : AtlasDbContextBase
    {
        services.AddDbContext<TContext>((provider, optionsBuilder) =>
        {
            PostgreSqlOptions options = provider.GetRequiredService<IOptions<PostgreSqlOptions>>().Value;

            optionsBuilder.UseNpgsql(options.ConnectionString, npgsql =>
            {
                npgsql.MigrationsHistoryTable(options.MigrationsHistoryTable, options.Schema);
                npgsql.EnableRetryOnFailure(options.MaxRetryCount, options.MaxRetryDelay, null);
                npgsql.CommandTimeout(options.CommandTimeoutSeconds);
            });

            optionsBuilder.UseApplicationServiceProvider(provider);
            optionsBuilder.EnableSensitiveDataLogging(options.EnableSensitiveDataLogging);
            optionsBuilder.EnableDetailedErrors(options.EnableDetailedErrors);
        });

        services.AddScoped<AtlasDbContextBase>(provider => provider.GetRequiredService<TContext>());
        services.AddScoped<IUnitOfWork, UnitOfWork<TContext>>();

        return services;
    }
}
