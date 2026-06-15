using Atlas.Modules.Identity.Application.UserRoles;
using Atlas.Modules.Identity.Application.Users;
using Atlas.Modules.Identity.Contracts.Users;
using Atlas.Modules.Identity.Endpoints.Internal;
using MediatR;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace Atlas.Modules.Identity.Endpoints;

internal static class UserEndpoints
{
    public static void MapUserEndpoints(this IEndpointRouteBuilder builder)
    {
        RouteGroupBuilder group = builder.MapGroup("/api/users").WithTags("Users");

        group.MapPost("/", async (
            CreateUserRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new CreateUserCommand(
                request.Username,
                request.Email,
                request.Password,
                request.Phone,
                request.FullNameAr,
                request.FullNameEn,
                request.EmployeeId,
                request.ForcePasswordChange,
                performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(response => Results.Created($"/api/users/{response.UserId}", response));
        });

        group.MapGet("/{userId:long}", async (
            long userId,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetUserByIdQuery(userId), cancellationToken);
            return result.Match(Results.Ok);
        });

        group.MapGet("/", async (
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetUsersByTenantQuery(), cancellationToken);
            return result.Match(Results.Ok);
        });

        group.MapGet("/{userId:long}/roles", async (
            long userId,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetUserRolesQuery(userId), cancellationToken);
            return result.Match(Results.Ok);
        });
    }
}
