using Atlas.BuildingBlocks.Domain.Repositories;
using Microsoft.EntityFrameworkCore;

namespace Atlas.Persistence.PostgreSql.Query;

public static class SpecificationEvaluator
{
    public static IQueryable<TEntity> GetQuery<TEntity>(
        IQueryable<TEntity> inputQuery,
        ISpecification<TEntity> specification)
        where TEntity : class
    {
        IQueryable<TEntity> query = inputQuery;

        if (specification.Criteria is not null)
        {
            query = query.Where(specification.Criteria);
        }

        query = specification.Includes.Aggregate(
            query,
            (current, include) => current.Include(include));

        if (specification.OrderBy is not null)
        {
            query = query.OrderBy(specification.OrderBy);
        }
        else if (specification.OrderByDescending is not null)
        {
            query = query.OrderByDescending(specification.OrderByDescending);
        }

        if (specification.AsSplitQuery)
        {
            query = query.AsSplitQuery();
        }

        if (specification.IsPagingEnabled)
        {
            if (specification.Skip is { } skip)
            {
                query = query.Skip(skip);
            }

            if (specification.Take is { } take)
            {
                query = query.Take(take);
            }
        }

        if (specification.AsNoTracking)
        {
            query = query.AsNoTracking();
        }

        return query;
    }
}
