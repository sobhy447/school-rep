using Atlas.Modules.Identity.Application.UserRoles;
using Atlas.Modules.Identity.Contracts.UserRoles;
using Atlas.Modules.Identity.Endpoints.Internal;
using MediatR;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace Atlas.Modules.Identity.Endpoints;

internal static class UserRoleEndpoints
{
    public static void MapUserRoleEndpoints(this IEndpointRouteBuilder builder)
    {
        RouteGroupBuilder group = builder.MapGroup("/api/user-roles").WithTags("UserRoles");

        group.MapPost("/", async (
            AssignRoleRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new AssignRoleToUserCommand(
                request.UserId,
                request.RoleId,
                request.CompanyId,
                request.ExpiresAtUtc,
                performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(response => Results.Created($"/api/user-roles/{response.UserRoleId}", response));
        });
    }
}
