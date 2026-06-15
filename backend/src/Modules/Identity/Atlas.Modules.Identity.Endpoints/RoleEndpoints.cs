using Atlas.Modules.Identity.Application.RolePermissions;
using Atlas.Modules.Identity.Application.Roles;
using Atlas.Modules.Identity.Contracts.RolePermissions;
using Atlas.Modules.Identity.Contracts.Roles;
using Atlas.Modules.Identity.Endpoints.Internal;
using MediatR;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace Atlas.Modules.Identity.Endpoints;

internal static class RoleEndpoints
{
    public static void MapRoleEndpoints(this IEndpointRouteBuilder builder)
    {
        RouteGroupBuilder group = builder.MapGroup("/api/roles").WithTags("Roles");

        group.MapPost("/", async (
            CreateRoleRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new CreateRoleCommand(request.RoleCode, request.NameAr, request.NameEn, performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(response => Results.Created($"/api/roles/{response.RoleId}", response));
        });

        group.MapGet("/{roleId:long}", async (
            long roleId,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetRoleByIdQuery(roleId), cancellationToken);
            return result.Match(Results.Ok);
        });

        group.MapGet("/", async (
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetRolesByTenantQuery(), cancellationToken);
            return result.Match(Results.Ok);
        });

        group.MapPost("/{roleId:long}/permissions", async (
            long roleId,
            GrantPermissionRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new GrantPermissionToRoleCommand(roleId, request.PermissionId, performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(response => Results.Created($"/api/roles/{roleId}/permissions/{response.PermissionId}", response));
        });

        group.MapGet("/{roleId:long}/permissions", async (
            long roleId,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetRolePermissionsQuery(roleId), cancellationToken);
            return result.Match(Results.Ok);
        });
    }
}
