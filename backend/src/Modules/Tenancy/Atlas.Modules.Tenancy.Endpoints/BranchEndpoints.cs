using Atlas.Modules.Tenancy.Application.Branches;
using Atlas.Modules.Tenancy.Contracts.Branches;
using Atlas.Modules.Tenancy.Endpoints.Internal;
using MediatR;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace Atlas.Modules.Tenancy.Endpoints;

internal static class BranchEndpoints
{
    public static void MapBranchEndpoints(this IEndpointRouteBuilder builder)
    {
        RouteGroupBuilder group = builder.MapGroup("/api/branches").WithTags("Branches");

        group.MapPost("/", async (
            CreateBranchRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new CreateBranchCommand(
                request.CompanyId,
                request.BranchCode,
                request.NameAr,
                request.NameEn,
                request.Address,
                request.Phone,
                request.IsMainBranch,
                performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(response => Results.Created($"/api/branches/{response.BranchId}", response));
        });

        group.MapPut("/{branchId:long}", async (
            long branchId,
            UpdateBranchRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new UpdateBranchCommand(
                branchId,
                request.NameAr,
                request.NameEn,
                request.Address,
                request.Phone,
                request.IsMainBranch,
                performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(Results.Ok);
        });

        group.MapGet("/{branchId:long}", async (
            long branchId,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetBranchByIdQuery(branchId), cancellationToken);
            return result.Match(Results.Ok);
        });

        group.MapGet("/by-company/{companyId:long}", async (
            long companyId,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetBranchesByCompanyQuery(companyId), cancellationToken);
            return result.Match(Results.Ok);
        });
    }
}
