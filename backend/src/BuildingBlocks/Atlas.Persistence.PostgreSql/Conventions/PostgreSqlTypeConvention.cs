using Microsoft.EntityFrameworkCore;

namespace Atlas.Persistence.PostgreSql.Conventions;

public static class PostgreSqlTypeConvention
{
    public static void ApplyAtlasTypeConventions(this ModelConfigurationBuilder configurationBuilder)
    {
        configurationBuilder.Properties<DateTime>().HaveColumnType("timestamptz");
        configurationBuilder.Properties<DateTimeOffset>().HaveColumnType("timestamptz");
        configurationBuilder.Properties<decimal>().HavePrecision(18, 4);
    }
}
