using Atlas.Modules.Identity.Application.Permissions;
using Atlas.Modules.Identity.Contracts.Permissions;
using Atlas.Modules.Identity.Endpoints.Internal;
using MediatR;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace Atlas.Modules.Identity.Endpoints;

internal static class PermissionEndpoints
{
    public static void MapPermissionEndpoints(this IEndpointRouteBuilder builder)
    {
        RouteGroupBuilder group = builder.MapGroup("/api/permissions").WithTags("Permissions");

        group.MapPost("/", async (
            CreatePermissionRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new CreatePermissionCommand(
                request.PermissionCode,
                request.Module,
                request.Action,
                request.Scope,
                request.DescriptionAr,
                request.DescriptionEn,
                performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(response => Results.Created($"/api/permissions/{response.PermissionId}", response));
        });

        group.MapGet("/", async (
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetPermissionsQuery(), cancellationToken);
            return result.Match(Results.Ok);
        });
    }
}
