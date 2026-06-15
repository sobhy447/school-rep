using Atlas.Modules.Tenancy.Application.Companies;
using Atlas.Modules.Tenancy.Contracts.Companies;
using Atlas.Modules.Tenancy.Endpoints.Internal;
using MediatR;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Routing;

namespace Atlas.Modules.Tenancy.Endpoints;

internal static class CompanyEndpoints
{
    public static void MapCompanyEndpoints(this IEndpointRouteBuilder builder)
    {
        RouteGroupBuilder group = builder.MapGroup("/api/companies").WithTags("Companies");

        group.MapPost("/", async (
            CreateCompanyRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new CreateCompanyCommand(
                request.CompanyCode,
                request.NameAr,
                request.NameEn,
                request.BaseCurrencyId,
                request.TaxNumber,
                request.RegistrationNo,
                request.AddressLine1,
                request.AddressLine2,
                request.City,
                request.CountryCode,
                request.Phone,
                request.Email,
                request.LogoUrl,
                request.FiscalYearStartMonth,
                performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(response => Results.Created($"/api/companies/{response.CompanyId}", response));
        });

        group.MapPut("/{companyId:long}", async (
            long companyId,
            UpdateCompanyRequest request,
            HttpContext httpContext,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            if (!CurrentUser.TryGetUserId(httpContext, out long performedBy))
            {
                return Results.Unauthorized();
            }

            var command = new UpdateCompanyCommand(
                companyId,
                request.NameAr,
                request.NameEn,
                request.BaseCurrencyId,
                request.TaxNumber,
                request.RegistrationNo,
                request.AddressLine1,
                request.AddressLine2,
                request.City,
                request.CountryCode,
                request.Phone,
                request.Email,
                request.LogoUrl,
                request.FiscalYearStartMonth,
                performedBy);

            var result = await sender.Send(command, cancellationToken);
            return result.Match(Results.Ok);
        });

        group.MapGet("/{companyId:long}", async (
            long companyId,
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetCompanyByIdQuery(companyId), cancellationToken);
            return result.Match(Results.Ok);
        });

        group.MapGet("/", async (
            ISender sender,
            CancellationToken cancellationToken) =>
        {
            var result = await sender.Send(new GetCompaniesByTenantQuery(), cancellationToken);
            return result.Match(Results.Ok);
        });
    }
}
