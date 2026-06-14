using System.Linq.Expressions;

namespace Atlas.BuildingBlocks.Domain.Repositories;

public interface ISpecification<TEntity>
    where TEntity : class
{
    Expression<Func<TEntity, bool>>? Criteria { get; }

    IReadOnlyList<Expression<Func<TEntity, object>>> Includes { get; }

    Expression<Func<TEntity, object>>? OrderBy { get; }

    Expression<Func<TEntity, object>>? OrderByDescending { get; }

    int? Skip { get; }

    int? Take { get; }

    bool IsPagingEnabled { get; }

    bool AsNoTracking { get; }

    bool AsSplitQuery { get; }
}
