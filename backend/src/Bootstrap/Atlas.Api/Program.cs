using Atlas.Api.Infrastructure;
using Atlas.BuildingBlocks.Application;
using Atlas.BuildingBlocks.Domain.Events;
using Atlas.BuildingBlocks.Domain.UnitOfWork;
using Atlas.Caching.Redis;
using Atlas.Messaging.Outbox;
using Atlas.Modules.Identity.Endpoints;
using Atlas.Modules.Identity.Infrastructure;
using Atlas.Modules.Tenancy.Endpoints;
using Atlas.Modules.Tenancy.Infrastructure;
using Atlas.MultiTenancy;
using Atlas.Persistence.PostgreSql;
using Atlas.Persistence.PostgreSql.Context;
using Microsoft.AspNetCore.Diagnostics.HealthChecks;
using Microsoft.Extensions.DependencyInjection.Extensions;
using Microsoft.OpenApi.Models;
using Serilog;

WebApplicationBuilder builder = WebApplication.CreateBuilder(args);

builder.Host.UseSerilog((context, services, configuration) => configuration
    .ReadFrom.Configuration(context.Configuration)
    .ReadFrom.Services(services)
    .Enrich.FromLogContext());

string postgresConnectionString = builder.Configuration.GetConnectionString("Postgres")
    ?? throw new InvalidOperationException("Connection string 'Postgres' is not configured.");
string redisConnectionString = builder.Configuration.GetConnectionString("Redis")
    ?? throw new InvalidOperationException("Connection string 'Redis' is not configured.");

IServiceCollection services = builder.Services;

services.AddProblemDetails();
services.AddExceptionHandler<GlobalExceptionHandler>();

services.AddEndpointsApiExplorer();
services.AddSwaggerGen(options =>
    options.SwaggerDoc("v1", new OpenApiInfo { Title = "Atlas ERP API", Version = "v1" }));

services.AddApplication(
    typeof(Atlas.Modules.Tenancy.Application.DependencyInjection).Assembly,
    typeof(Atlas.Modules.Identity.Application.DependencyInjection).Assembly);

services.AddMultiTenancy(options =>
{
    options.AllowHeaderResolution = builder.Environment.IsDevelopment();
    options.BypassPaths = ["/health", "/health/live", "/swagger", "/openapi"];
});

services.AddAtlasPersistence(options =>
{
    options.ConnectionString = postgresConnectionString;
    options.EnableDetailedErrors = builder.Environment.IsDevelopment();
    options.EnableSensitiveDataLogging = false;
});

services.AddAtlasCaching(options => options.ConnectionString = redisConnectionString);
services.AddOutboxMessaging();

services.AddScoped<IDomainEventDispatcher, MediatRDomainEventDispatcher>();

services.AddTenancyInfrastructure();
services.AddIdentityInfrastructure();

services.RemoveAll<IUnitOfWork>();
services.AddScoped<IUnitOfWork, CompositeUnitOfWork>();

services.AddHealthChecks()
    .AddNpgSql(postgresConnectionString, name: "postgres")
    .AddRedis(redisConnectionString, name: "redis");

WebApplication app = builder.Build();

app.UseExceptionHandler();
app.UseSerilogRequestLogging();

if (app.Environment.IsDevelopment())
{
    app.UseSwagger();
    app.UseSwaggerUI();
}

app.UseMultiTenancy();

app.MapHealthChecks("/health");
app.MapHealthChecks("/health/live", new HealthCheckOptions { Predicate = _ => false });

app.MapTenancyEndpoints();
app.MapIdentityEndpoints();

app.Run();

public partial class Program;
