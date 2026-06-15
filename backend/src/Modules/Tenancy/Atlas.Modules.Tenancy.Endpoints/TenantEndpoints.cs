using Atlas.Modules.Tenancy.Application.Tenants;
using Atlas.Modules.Tenancy.Contracts.Tenants;
using Atlas.Modules.Tenancy.Endpoints.Internal;
using MediatR;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace Atlas.Modules.Tenancy.Endpoints;

internal static class TenantEndpoints
{
    public static void MapTenantEndpoints(this IEndpointRouteBuilder builder)
    {
        RouteGroupBuilder group = builder.MapGroup("/api/tenants").WithTags("Tenants");

        group.MapPost("/", async (
            CreateTenantRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new CreateTenantCommand(
                request.TenantCode,
                request.NameAr,
                request.NameEn,
                request.BaseCurrencyId,
                request.MaxCompanies,
                request.MaxUsers,
                request.DefaultLanguage,
                request.TimeZone,
                performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(response => Results.Created($"/api/tenants/{response.TenantId}", response));
        });

        group.MapPut("/{tenantId:long}", async (
            long tenantId,
            UpdateTenantRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new UpdateTenantCommand(
                tenantId,
                request.NameAr,
                request.NameEn,
                request.MaxCompanies,
                request.MaxUsers,
                request.DefaultLanguage,
                request.TimeZone,
                request.SubscriptionStatus,
                performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(Results.Ok);
        });

        group.MapGet("/{tenantId:long}", async (
            long tenantId,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetTenantByIdQuery(tenantId), cancellationToken);
            return result.Match(Results.Ok);
        });
    }
}
