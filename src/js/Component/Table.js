function Table() {
	return (
        <div>
            <table className="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th> Name </th>
                </tr>
                </thead>
                    <tbody id="the-comment-list">
                        <td>
                            Mamun
                        </td>
                    </tbody>
                <tfoot>
                <tr>
                    <th> Name </th>
                </tr>
                </tfoot>
            </table>
        </div>
    );
}

export default Table;