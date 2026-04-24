<style>
    .matchPlayedItemView{
        display: flex;
        justify-content: space-between;
        padding: 10px 30px;
    }
    .matchPlayedItemView:nth-child(odd) {
        background: rgba(0,0,0,0.04);
    }
    .matchPlayedItemView div:first-child{
        height: 25px;
        width: 25px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(0,0,0,0.6);
        background: rgba(0,0,0,0.1);
        margin-right: 15px;
    }
    .matchPlayedItemView div:nth-child(2){
        flex: 1;
    }
    .matchPlayedItemView div:nth-child(2) p:first-child{
        font-size: 17px;
        color: #424949;
    }
    .matchPlayedItemView div:nth-child(2) p:last-child{
        font-size: 13px;
        margin-top: 8px;
    }
    .matchPlayedItemView p span{
        color: #e67e22;
        text-transform: uppercase;
    }
    .matchPlayedItemView a{
        display: block;
        height: fit-content;
        padding: 5px 10px;
        border-radius: 5px;
        text-decoration: none;
        color: rgba(0,0,0,0.8);
        background: rgba(0,0,0,0.09);
    }
</style>

<div class="w-100 match_played_records_view">
</div>
