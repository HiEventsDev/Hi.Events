import {create} from 'zustand'
import {GenericDataResponse, Order} from "../types.ts";

interface AppStore {
    createOrderResponse: GenericDataResponse<Order> | null
}

export const useAppStore = create<AppStore>()(() => ({
    createOrderResponse: null,
}))