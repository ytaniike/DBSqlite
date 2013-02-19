using System;
using System.Collections;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Data.SQLite;
using System.IO;
using System.Linq;
using System.Text.RegularExpressions;
using System.Data;

namespace dbsqlite
{
    public class DBSqlite
    {
        // usr path
        public static string sDBPath = Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData);

        // last insert row id
        public int iLastInsertRowID = 0;

        // db sqlite object
        private SQLiteConnection oDB    = null;

        // db sqlite command object
        private SQLiteCommand oCommand  = null;

        // constructor
        public DBSqlite(string sDBName)
        {
            // init db sqlite object
            this.oDB = new SQLiteConnection();

            // set path and filename where we will save/load the sqlitedb
            string sPathToDB = Path.Combine(DBSqlite.sDBPath, sDBName);

            // init connection
            this.oDB.ConnectionString = "Data Source=" + sPathToDB;
            this.oDB.Open();
        }

        private string querySingle(string sSQL, Dictionary<string, string> aSQL)
        {
            string sReturn = "";

            // create new command
            this.oCommand = this.oDB.CreateCommand();
            this.oCommand.CommandType = CommandType.Text;

            // replace placeholder - prevent sql injection
            this.oCommand.CommandText = sSQL = this.replace(sSQL, aSQL);

            try
            {
                SQLiteDataReader oReader = this.oCommand.ExecuteReader();
                oReader.Read();
                sReturn = oReader[0].ToString();
                oReader.Close();
            }
            catch (Exception oExecption)
            {
                throw new Exception(oExecption.Message);
            }

            return sReturn;
        }

        public string fetchOne(string sSQL, Dictionary<string, string> aSQL)
        {
            string sReturn = this.querySingle(sSQL, aSQL);
            return sReturn;
        }

        public ArrayList fetchCol(string sSQL, Dictionary<string, string> aSQL)
        {
            DataTable aPragmaData = this.fetchAll(sSQL, aSQL);

            ArrayList aCol = new ArrayList();

            // each all cols
            foreach (DataRow oRow in aPragmaData.Rows)
            {
                foreach (DataColumn oColumn in aPragmaData.Columns)
                {
                    aCol.Add(oRow[oColumn].ToString());
                }
            }

            return aCol;
        }

        public Dictionary<string, string> fetchRow(string sSQL, Dictionary<string, string> aSQL)
        {
            DataTable aPragmaData = this.fetchAll(sSQL, aSQL);

            Dictionary<string, string> aRow = new Dictionary<string, string>();

            // each all cols
            foreach (DataRow oRow in aPragmaData.Rows)
            {
                foreach (DataColumn oColumn in aPragmaData.Columns)
                {
                    aRow.Add(oColumn.ColumnName, oRow[oColumn].ToString());
                }

                break;
            }

            return aRow;
        }

        public DataTable fetchAll(string sSQL, Dictionary<string, string> aSQL)
        {
            DataTable oDataTable = new DataTable();

            // create new command
            this.oCommand = this.oDB.CreateCommand();
            this.oCommand.CommandType = System.Data.CommandType.Text;

            // replace placeholder - prevent sql injection
            this.oCommand.CommandText = sSQL = this.replace(sSQL, aSQL);

            try
            {
                SQLiteDataReader oReader = this.oCommand.ExecuteReader();
                oDataTable.Load(oReader);
                oReader.Close();
            }
            catch (Exception oExecption)
            {
                throw new Exception(oExecption.Message);
            }

            return oDataTable;
        }

        public int insert(string sTable, Dictionary<string, string> aData)
        {
            sTable = this.escapeTablename(sTable);

            // check if table exists
            Dictionary<string, string> aSQL = new Dictionary<string, string>();
            aSQL.Add("sTable", sTable);
            string sSQL = @"
                SELECT
                    COUNT(*)
                FROM
				    `sqlite_master`
			    WHERE
				    `type` = 'table' AND
				    `name` = :sTable
            ";
            int iCountTable = Convert.ToInt32(this.fetchOne(sSQL, aSQL));

            if (iCountTable != 1)
            {
                throw new Exception("DB - insert: table does not exist");
            }

            // get table informations
            aSQL.Clear();
            aSQL.Add("sTable", sTable);
            sSQL = @"
                PRAGMA table_info(#sTable);
            ";
            DataTable aPragmaData = this.fetchAll(sSQL, aSQL);

            if (aPragmaData.Rows.Count.Equals(0))
            {
                throw new Exception("DB - insert: table got no cols");
            }

            ArrayList aColsAvailable = new ArrayList();

            // each all cols
            foreach (DataRow oRow in aPragmaData.Rows)
            {
                foreach (DataColumn oColumn in aPragmaData.Columns)
                {
                    if (oColumn.ColumnName == "name")
                    {
                        aColsAvailable.Add(oRow[oColumn].ToString());
                    }
                }
            }


            // prepare asql ssql
            aSQL.Clear();
            int iCount = 0;
            string sSQLInsertHeader = "";
            string sSQLInsertBody = "";

            foreach (KeyValuePair<string, string> oPair in aData)
            {
                // check if the user given col exists in this table
                if (!aColsAvailable.Contains(oPair.Key))
                {
                    throw new Exception("DB - insert: col \"" + oPair.Key + "\" does not exist");
                }

                // insert data
                aSQL.Add("col_name_" + Convert.ToString(iCount), oPair.Key);
                aSQL.Add("col_value_" + Convert.ToString(iCount), oPair.Value);

                // extend header
                sSQLInsertHeader += "#col_name_" + Convert.ToString(iCount);
                sSQLInsertBody += ":col_value_" + Convert.ToString(iCount);

                if (iCount + 1 < aData.Count)
                {
                    sSQLInsertHeader += ", ";
                    sSQLInsertBody += ", ";
                }

                iCount++;
            }

            sSQL = "INSERT INTO `" + sTable + "` (" + sSQLInsertHeader + ") VALUES (" + sSQLInsertBody + ")";

            // create new command
            this.oCommand = this.oDB.CreateCommand();
            this.oCommand.CommandType = CommandType.Text;

            // replace placeholder - prevent sql injection
            this.oCommand.CommandText = sSQL = this.replace(sSQL, aSQL);

            try
            {
                // fire query
                this.oCommand.ExecuteNonQuery();

                // get last inserted id
                // create new command
                this.oCommand = this.oDB.CreateCommand();
                this.oCommand.CommandType = CommandType.Text;
                this.oCommand.CommandText = "SELECT last_insert_rowid()";
                this.iLastInsertRowID = Convert.ToInt32(this.oCommand.ExecuteScalar());
            }
            catch (Exception oExecption)
            {
                throw new Exception(oExecption.Message);
            }

            return this.iLastInsertRowID;
        }

        public string replace(string sSQL, Dictionary<string, string> aSQL)
        {
            // sort aSQL by keystring by length descending
            aSQL = (
                from oPair in aSQL
                orderby oPair.Key descending
                select oPair
            ).ToDictionary(oPair => oPair.Key, oPair => oPair.Value);

            // each all strings that we sould escape
            foreach(KeyValuePair<string, string> oPair in aSQL)
            {
                if (sSQL.Contains(":" + oPair.Key))
                {
                    sSQL = sSQL.Replace(":" + oPair.Key, "@" + oPair.Key);
                    this.oCommand.Parameters.Add(new SQLiteParameter("@" + oPair.Key, oPair.Value));
                }
                else if(sSQL.Contains("#" + oPair.Key))
                {
                    // clear tablename string
                    sSQL = sSQL.Replace("#" + oPair.Key, "`" + this.escapeTablename(oPair.Value) + "`");
                }
            }

            return sSQL;
        }

        private string escapeTablename(string sTablename)
        {
            // only allow some chars for tablenames
            return Regex.Replace(sTablename, "[^a-zA-Z0-9_-]", "");
        }
    }
}
