using System.Linq.Expressions;

namespace Atlas.BuildingBlocks.Domain.Repositories;

public abstract class Specification<TEntity> : ISpecification<TEntity>
    where TEntity : class
{
    private readonly List<Expression<Func<TEntity, object>>> _includes = [];

    protected Specification(Expression<Func<TEntity, bool>>? criteria = null) => Criteria = criteria;

    public Expression<Func<TEntity, bool>>? Criteria { get; }

    public IReadOnlyList<Expression<Func<TEntity, object>>> Includes => _includes;

    public Expression<Func<TEntity, object>>? OrderBy { get; private set; }

    public Expression<Func<TEntity, object>>? OrderByDescending { get; private set; }

    public int? Skip { get; private set; }

    public int? Take { get; private set; }

    public bool IsPagingEnabled { get; private set; }

    public bool AsNoTracking { get; private set; }

    public bool AsSplitQuery { get; private set; }

    protected void AddInclude(Expression<Func<TEntity, object>> includeExpression) =>
        _includes.Add(includeExpression);

    protected void ApplyOrderBy(Expression<Func<TEntity, object>> orderByExpression) =>
        OrderBy = orderByExpression;

    protected void ApplyOrderByDescending(Expression<Func<TEntity, object>> orderByDescendingExpression) =>
        OrderByDescending = orderByDescendingExpression;

    protected void ApplyPaging(int skip, int take)
    {
        Skip = skip;
        Take = take;
        IsPagingEnabled = true;
    }

    protected void ApplyNoTracking() => AsNoTracking = true;

    protected void ApplySplitQuery() => AsSplitQuery = true;
}
